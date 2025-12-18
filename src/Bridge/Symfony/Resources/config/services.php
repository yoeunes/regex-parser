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
use RegexParser\Bridge\Symfony\Extractor\RegexPatternExtractor;
use RegexParser\Bridge\Symfony\Service\RegexAnalysisService;
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

    $services->set('regex_parser.command.lint', RegexLintCommand::class)
        ->arg('$regexAnalysis', service('regex_parser.service.regex_analysis'))
        ->arg('$editorFormat', param('regex_parser.editor_format'))
        ->arg('$defaultPaths', param('regex_parser.paths'))
        ->arg('$excludePaths', param('regex_parser.exclude_paths'))
        ->tag('console.command')
        ->public();
};
