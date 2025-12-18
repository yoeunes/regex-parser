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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    $services->set(RouteRequirementAnalyzer::class, RouteRequirementAnalyzer::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$warningThreshold', param('regex_parser.analysis.warning_threshold'))
        ->arg('$redosThreshold', param('regex_parser.redos.threshold'))
        ->arg('$ignoredPatterns', param('regex_parser.redos.ignored_patterns'));

    $services->set(ValidatorRegexAnalyzer::class, ValidatorRegexAnalyzer::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$warningThreshold', param('regex_parser.analysis.warning_threshold'))
        ->arg('$redosThreshold', param('regex_parser.redos.threshold'))
        ->arg('$ignoredPatterns', param('regex_parser.redos.ignored_patterns'));

    // Configure extractor with the determined implementation
    $services->set('regex_parser.extractor', RegexPatternExtractor::class)
        ->args([
            '$extractor' => service('regex_parser.extractor.instance')->nullOnInvalid(),
        ]);

    $services->set('regex_parser.service.regex_analysis', RegexAnalysisService::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$extractor', service('regex_parser.extractor')->nullOnInvalid());

    $services->set('regex_parser.service.route_validation', RouteValidationService::class)
        ->arg('$analyzer', service(RouteRequirementAnalyzer::class))
        ->arg('$router', service(RouterInterface::class)->nullOnInvalid());

    $services->set('regex_parser.service.validator_validation', ValidatorValidationService::class)
        ->arg('$analyzer', service(ValidatorRegexAnalyzer::class))
        ->arg('$validator', service(ValidatorInterface::class)->nullOnInvalid())
        ->arg('$validatorLoader', service(LoaderInterface::class)->nullOnInvalid());

    $services->set('regex_parser.command.lint', RegexLintCommand::class)
        ->arg('$regexAnalysis', service('regex_parser.service.regex_analysis'))
        ->arg('$routeValidation', service('regex_parser.service.route_validation')->nullOnInvalid())
        ->arg('$validatorValidation', service('regex_parser.service.validator_validation')->nullOnInvalid())
        ->arg('$editorFormat', param('regex_parser.editor_format'))
        ->arg('$defaultPaths', param('regex_parser.paths'))
        ->arg('$excludePaths', param('regex_parser.exclude_paths'))
        ->arg('$defaultRedosThreshold', param('regex_parser.redos.threshold'))
        ->tag('console.command')
        ->public();
};
