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

use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Bridge\Symfony\Command\RegexLintCommand;
use RegexParser\Bridge\Symfony\Extractor\RegexPatternExtractor;
use RegexParser\Bridge\Symfony\Service\RegexAnalysisService;
use RegexParser\Bridge\Symfony\Service\RouteValidationService;
use RegexParser\Bridge\Symfony\Service\ValidatorValidationService;
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

    $services->set('regex_parser.regex', Regex::class)
        ->factory([Regex::class, 'create'])
        ->arg('$options', [
            'max_pattern_length' => param('regex_parser.max_pattern_length'),
            'max_lookbehind_length' => param('regex_parser.max_lookbehind_length'),
            'cache' => service('regex_parser.cache'),
            'redos_ignored_patterns' => param('regex_parser.redos.ignored_patterns'),
        ])
        ->public();

    // Aliases for autowiring
    $services->alias(Regex::class, 'regex_parser.regex')
        ->public();

    // Configure extractor with the determined implementation
    $services->set('regex_parser.extractor', RegexPatternExtractor::class)
        ->args([
            '$extractor' => service('regex_parser.extractor.instance')->nullOnInvalid(),
        ]);

    $services->set('regex_parser.service.regex_analysis', RegexAnalysisService::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$extractor', service('regex_parser.extractor')->nullOnInvalid());

    $services->set('regex_parser.service.route_validation', RouteValidationService::class)
        ->args([
            '$router' => service('router')->nullOnInvalid(),
            '$analyzer' => service(RouteRequirementAnalyzer::class),
        ]);

    $services->set('regex_parser.service.validator_validation', ValidatorValidationService::class)
        ->args([
            '$validator' => service('validator')->nullOnInvalid(),
            '$analyzer' => service(ValidatorRegexAnalyzer::class),
        ]);

    $services->set('regex_parser.command.lint', RegexLintCommand::class)
        ->arg('$analysis', service('regex_parser.service.regex_analysis'))
        ->arg('$routeValidation', service('regex_parser.service.route_validation')->nullOnInvalid())
        ->arg('$validatorValidation', service('regex_parser.service.validator_validation')->nullOnInvalid())
        ->arg('$editorUrl', param('regex_parser.editor_format'))
        ->arg('$paths', param('regex_parser.paths'))
        ->arg('$exclude', param('regex_parser.exclude_paths'))
        ->arg('$minSavings', 1)
        ->tag('console.command')
        ->public();

    // Analyzer services
    $services->set(RouteRequirementAnalyzer::class)
        ->args([
            '$regex' => service('regex_parser.regex'),
            '$warningThreshold' => 1,
            '$redosThreshold' => 'high',
            '$ignoredPatterns' => [],
        ])
        ->tag('regex_parser.analyzer');

    $services->set(ValidatorRegexAnalyzer::class)
        ->args([
            '$regex' => service('regex_parser.regex'),
            '$warningThreshold' => 1,
            '$redosThreshold' => 'high',
            '$ignoredPatterns' => [],
        ])
        ->tag('regex_parser.analyzer');
};
