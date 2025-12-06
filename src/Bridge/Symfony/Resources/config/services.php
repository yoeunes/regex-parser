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

use Psr\Log\LoggerInterface;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Bridge\Symfony\CacheWarmer\RegexParserCacheWarmer;
use RegexParser\Bridge\Symfony\Command\RegexParserValidateCommand;
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
            'cache' => param('regex_parser.cache'),
            'redos_ignored_patterns' => param('regex_parser.analysis.ignore_patterns'),
        ])
        ->public();

    // Aliases for autowiring
    $services->alias(Regex::class, 'regex_parser.regex')
        ->public();

    $services->set(RouteRequirementAnalyzer::class, RouteRequirementAnalyzer::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$warningThreshold', param('regex_parser.analysis.warning_threshold'))
        ->arg('$redosThreshold', param('regex_parser.analysis.redos_threshold'))
        ->arg('$ignoredPatterns', param('regex_parser.analysis.ignore_patterns'));

    $services->set(ValidatorRegexAnalyzer::class, ValidatorRegexAnalyzer::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$warningThreshold', param('regex_parser.analysis.warning_threshold'))
        ->arg('$redosThreshold', param('regex_parser.analysis.redos_threshold'))
        ->arg('$ignoredPatterns', param('regex_parser.analysis.ignore_patterns'));

    $services->set('regex_parser.cache_warmer', RegexParserCacheWarmer::class)
        ->arg('$analyzer', service(RouteRequirementAnalyzer::class))
        ->arg('$router', service(RouterInterface::class)->nullOnInvalid())
        ->arg('$logger', service(LoggerInterface::class)->nullOnInvalid())
        ->arg('$validatorAnalyzer', service(ValidatorRegexAnalyzer::class))
        ->arg('$validator', service(ValidatorInterface::class)->nullOnInvalid())
        ->arg('$validatorLoader', service(LoaderInterface::class)->nullOnInvalid())
        ->tag('kernel.cache_warmer');

    $services->set('regex_parser.command.validate', RegexParserValidateCommand::class)
        ->arg('$analyzer', service(RouteRequirementAnalyzer::class))
        ->arg('$router', service(RouterInterface::class)->nullOnInvalid())
        ->arg('$validatorAnalyzer', service(ValidatorRegexAnalyzer::class))
        ->arg('$validator', service(ValidatorInterface::class)->nullOnInvalid())
        ->arg('$validatorLoader', service(LoaderInterface::class)->nullOnInvalid())
        ->tag('console.command')
        ->public();
};
