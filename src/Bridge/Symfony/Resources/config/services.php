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

use RegexParser\Bridge\Symfony\Command\RegexLintCommand;
use RegexParser\Bridge\Symfony\Service\RegexAnalysisService;
use RegexParser\Bridge\Symfony\Service\RouteValidationService;
use RegexParser\Bridge\Symfony\Service\ValidatorValidationService;
use RegexParser\Regex;

/*
 * Base services for the RegexParser library.
 *
 * These services are always loaded when the bundle is enabled.
 */
/** @internal */
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
    $services->set('regex_parser.extractor', 'RegexParser\\Bridge\\Symfony\\Extractor\\RegexPatternExtractor')
        ->args([
            '$extractor' => service('regex_parser.extractor.instance')->nullOnInvalid(),
        ]);

    $services->set('regex_parser.service.regex_analysis', RegexAnalysisService::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$extractor', service('regex_parser.extractor')->nullOnInvalid());

    $services->set('regex_parser.pattern_sources', 'RegexParser\\Bridge\\Symfony\\Extractor\\RegexPatternSourceCollection')
        ->args([
            '$sources' => tagged_iterator('regex_parser.pattern_source'),
        ]);

    $services->set('RegexParser\\Bridge\\Symfony\\Extractor\\PhpRegexPatternSource')
        ->args([
            '$extractor' => service('regex_parser.extractor'),
        ])
        ->tag('regex_parser.pattern_source');

    $services->set('RegexParser\\Bridge\\Symfony\\Extractor\\RouteRegexPatternSource')
        ->args([
            '$router' => service('router')->nullOnInvalid(),
        ])
        ->tag('regex_parser.pattern_source');

    $services->set('RegexParser\\Bridge\\Symfony\\Extractor\\ValidatorRegexPatternSource')
        ->args([
            '$validator' => service('validator')->nullOnInvalid(),
            '$validatorLoader' => service('validator.mapping.loader')->nullOnInvalid(),
        ])
        ->tag('regex_parser.pattern_source');

    $services->set('regex_parser.service.regex_lint', 'RegexParser\\Bridge\\Symfony\\Service\\RegexLintService')
        ->args([
            '$analysis' => service('regex_parser.service.regex_analysis'),
            '$sources' => service('regex_parser.pattern_sources'),
            '$issueProviders' => tagged_iterator('regex_parser.lint_issue_provider'),
        ]);

    $services->set('regex_parser.service.route_validation', RouteValidationService::class)
        ->args([
            '$analysis' => service('regex_parser.service.regex_analysis'),
            '$router' => service('router')->nullOnInvalid(),
        ]);

    $services->set('regex_parser.service.validator_validation', ValidatorValidationService::class)
        ->args([
            '$validator' => service('validator')->nullOnInvalid(),
            '$validatorLoader' => service('validator.mapping.loader')->nullOnInvalid(),
            '$analyzer' => service('RegexParser\\Bridge\\Symfony\\Analyzer\\ValidatorRegexAnalyzer'),
        ])
        ->tag('regex_parser.lint_issue_provider');

    $services->set('regex_parser.command.lint', RegexLintCommand::class)
        ->arg('$lint', service('regex_parser.service.regex_lint'))
        ->arg('$analysis', service('regex_parser.service.regex_analysis'))
        ->arg('$editorUrl', param('regex_parser.editor_format'))
        ->tag('console.command')
        ->public();

    // Analyzer services
    $services->set('RegexParser\\Bridge\\Symfony\\Analyzer\\RouteRequirementAnalyzer')
        ->args([
            '$regex' => service('regex_parser.regex'),
            '$warningThreshold' => 1,
            '$redosThreshold' => 'high',
            '$ignoredPatterns' => [],
        ])
        ->tag('regex_parser.analyzer');

    $services->set('RegexParser\\Bridge\\Symfony\\Analyzer\\ValidatorRegexAnalyzer')
        ->args([
            '$regex' => service('regex_parser.regex'),
            '$warningThreshold' => 1,
            '$redosThreshold' => 'high',
            '$ignoredPatterns' => [],
        ])
        ->tag('regex_parser.analyzer');
};
