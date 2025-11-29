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
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Parser;
use RegexParser\Regex;
use RegexParser\RegexCompiler;

/*
 * Base services for the RegexParser library.
 *
 * These services are always loaded when the bundle is enabled.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->private();

    // Core parser and compiler
    $services->set('regex_parser.parser', Parser::class)
        ->arg('$options', [
            'max_pattern_length' => param('regex_parser.max_pattern_length'),
        ]);

    $services->set('regex_parser.compiler', RegexCompiler::class)
        ->arg('$options', [
            'max_pattern_length' => param('regex_parser.max_pattern_length'),
        ]);

    // Node visitors
    $services->set('regex_parser.visitor.validator', ValidatorNodeVisitor::class);
    $services->set('regex_parser.visitor.explain', ExplainVisitor::class);
    $services->set('regex_parser.visitor.complexity_score', ComplexityScoreVisitor::class);
    $services->set('regex_parser.visitor.html_explain', HtmlExplainVisitor::class);
    $services->set('regex_parser.visitor.optimizer', OptimizerNodeVisitor::class);
    $services->set('regex_parser.visitor.sample_generator', SampleGeneratorVisitor::class);
    $services->set('regex_parser.visitor.dumper', DumperNodeVisitor::class);
    $services->set('regex_parser.visitor.compiler', CompilerNodeVisitor::class);

    // Main Regex facade service
    $services->set('regex_parser.regex', Regex::class)
        ->arg('$compiler', service('regex_parser.compiler'))
        ->arg('$validator', service('regex_parser.visitor.validator'))
        ->arg('$explainer', service('regex_parser.visitor.explain'))
        ->arg('$generator', service('regex_parser.visitor.sample_generator'))
        ->arg('$optimizer', service('regex_parser.visitor.optimizer'))
        ->arg('$dumper', service('regex_parser.visitor.dumper'))
        ->arg('$scorer', service('regex_parser.visitor.complexity_score'))
        ->public();

    // Aliases for autowiring
    $services->alias(Regex::class, 'regex_parser.regex')
        ->public();

    $services->alias(Parser::class, 'regex_parser.parser');
    $services->alias(RegexCompiler::class, 'regex_parser.compiler');
    $services->alias(ExplainVisitor::class, 'regex_parser.visitor.explain');
    $services->alias(ComplexityScoreVisitor::class, 'regex_parser.visitor.complexity_score');
};
