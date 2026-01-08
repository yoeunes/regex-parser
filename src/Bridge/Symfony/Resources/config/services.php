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

use RegexParser\Bridge\Symfony\Command\CompareCommand;
use RegexParser\Bridge\Symfony\Command\RegexLintCommand;
use RegexParser\Bridge\Symfony\Command\RegexRoutesCommand;
use RegexParser\Bridge\Symfony\Command\RegexSecurityCommand;
use RegexParser\Bridge\Symfony\Extractor\RouteRegexPatternSource;
use RegexParser\Bridge\Symfony\Extractor\ValidatorRegexPatternSource;
use RegexParser\Bridge\Symfony\Routing\RouteConflictAnalyzer;
use RegexParser\Bridge\Symfony\Routing\RouteControllerFileResolver;
use RegexParser\Bridge\Symfony\Routing\RouteRequirementNormalizer;
use RegexParser\Bridge\Symfony\Security\SecurityAccessControlAnalyzer;
use RegexParser\Bridge\Symfony\Security\SecurityConfigExtractor;
use RegexParser\Bridge\Symfony\Security\SecurityFirewallAnalyzer;
use RegexParser\Bridge\Symfony\Security\SecurityPatternNormalizer;
use RegexParser\Lint\ExtractorInterface;
use RegexParser\Lint\Formatter\FormatterRegistry;
use RegexParser\Lint\PhpRegexPatternSource;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintService;
use RegexParser\Lint\RegexPatternExtractor;
use RegexParser\Lint\RegexPatternSourceCollection;
use RegexParser\Regex;

/*
 * Base services for the RegexParser library.
 *
 * These services are always loaded when the bundle is enabled.
 */
/* @internal */
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
            'runtime_pcre_validation' => param('regex_parser.runtime_pcre_validation'),
        ])
        ->public();

    // Aliases for autowiring
    $services->alias(Regex::class, 'regex_parser.regex')
        ->public();

    // Configure extractor with the determined implementation
    $services->set('regex_parser.extractor', RegexPatternExtractor::class)
        ->args([
            '$extractor' => service(ExtractorInterface::class)->nullOnInvalid(),
        ]);

    $services->set('regex_parser.service.regex_analysis', RegexAnalysisService::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$extractor', service('regex_parser.extractor')->nullOnInvalid())
        ->arg('$warningThreshold', param('regex_parser.analysis.warning_threshold'))
        ->arg('$redosThreshold', param('regex_parser.redos.threshold'))
        ->arg('$ignoredPatterns', param('regex_parser.analysis.ignore_patterns'))
        ->arg('$redosIgnoredPatterns', param('regex_parser.redos.ignored_patterns'));

    $services->set('regex_parser.pattern_sources', RegexPatternSourceCollection::class)
        ->args([
            '$sources' => tagged_iterator('regex_parser.pattern_source'),
        ]);

    $services->set(PhpRegexPatternSource::class)
        ->args([
            '$extractor' => service('regex_parser.extractor'),
        ])
        ->tag('regex_parser.pattern_source');

    $services->set(RouteRegexPatternSource::class)
        ->args([
            '$patternNormalizer' => service(RouteRequirementNormalizer::class),
            '$router' => service('router')->nullOnInvalid(),
        ])
        ->tag('regex_parser.pattern_source');

    $services->set(ValidatorRegexPatternSource::class)
        ->args([
            '$validator' => service('validator')->nullOnInvalid(),
            '$validatorLoader' => service('validator.mapping.loader')->nullOnInvalid(),
        ])
        ->tag('regex_parser.pattern_source');

    $services->set('regex_parser.service.regex_lint', RegexLintService::class)
        ->args([
            '$analysis' => service('regex_parser.service.regex_analysis'),
            '$sources' => service('regex_parser.pattern_sources'),
        ]);

    $services->set('regex_parser.formatter_registry', FormatterRegistry::class);

    $services->set('regex_parser.command.lint', RegexLintCommand::class)
        ->arg('$lint', service('regex_parser.service.regex_lint'))
        ->arg('$analysis', service('regex_parser.service.regex_analysis'))
        ->arg('$formatterRegistry', service('regex_parser.formatter_registry'))
        ->arg('$defaultPaths', param('regex_parser.paths'))
        ->arg('$defaultExcludePaths', param('regex_parser.exclude_paths'))
        ->arg('$defaultOptimizations', param('regex_parser.optimizations'))
        ->arg('$editorUrl', param('regex_parser.editor_format'))
        ->tag('console.command')
        ->public();

    $services->set('regex_parser.command.compare', CompareCommand::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->tag('console.command')
        ->public();

    $services->set(RouteConflictAnalyzer::class)
        ->arg('$regex', service('regex_parser.regex'));

    $services->set('regex_parser.command.routes', RegexRoutesCommand::class)
        ->arg('$analyzer', service(RouteConflictAnalyzer::class))
        ->arg('$router', service('router')->nullOnInvalid())
        ->tag('console.command')
        ->public();

    $services->set(RouteRequirementNormalizer::class);

    $services->set(RouteControllerFileResolver::class);

    $services->set(SecurityPatternNormalizer::class);

    $services->set(SecurityConfigExtractor::class);

    $services->set(SecurityAccessControlAnalyzer::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$patternNormalizer', service(SecurityPatternNormalizer::class));

    $services->set(SecurityFirewallAnalyzer::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$patternNormalizer', service(SecurityPatternNormalizer::class));

    $services->set('regex_parser.command.security', RegexSecurityCommand::class)
        ->arg('$extractor', service(SecurityConfigExtractor::class))
        ->arg('$accessAnalyzer', service(SecurityAccessControlAnalyzer::class))
        ->arg('$firewallAnalyzer', service(SecurityFirewallAnalyzer::class))
        ->arg('$kernel', service('kernel')->nullOnInvalid())
        ->arg('$defaultRedosThreshold', param('regex_parser.redos.threshold'))
        ->tag('console.command')
        ->public();
};
