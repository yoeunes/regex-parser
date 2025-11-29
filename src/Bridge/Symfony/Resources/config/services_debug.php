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

use RegexParser\Bridge\Symfony\DataCollector\RegexCollector;
use RegexParser\Bridge\Symfony\Service\TraceableRouter;
use RegexParser\Bridge\Symfony\Service\TraceableValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/*
 * Debug services for the RegexParser bundle.
 *
 * These services are only loaded when profiler is enabled (typically in debug mode).
 * Includes the data collector and traceable decorators for Router and Validator.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->private();

    // Data collector for the Symfony Web Profiler
    $services->set('regex_parser.collector', RegexCollector::class)
        ->arg('$regex', service('regex_parser.regex'))
        ->arg('$explainVisitor', service('regex_parser.visitor.explain'))
        ->arg('$scoreVisitor', service('regex_parser.visitor.complexity_score'))
        ->arg('$redosThreshold', param('regex_parser.profiler.redos_threshold'))
        ->arg('$warningThreshold', param('regex_parser.profiler.warning_threshold'))
        ->tag('data_collector', [
            'template' => '@RegexParser/Profiler/regex_panel.html.twig',
            'id' => 'regex_parser',
            'priority' => 270,
        ])
        ->public();

    // Traceable Router decorator
    // Uses decoration_on_invalid: ignore to gracefully handle missing Router service
    $services->set('regex_parser.traceable_router', TraceableRouter::class)
        ->decorate(RouterInterface::class, null, 10, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->arg('$router', service('.inner'))
        ->arg('$collector', service('regex_parser.collector'));

    // Traceable Validator decorator
    // Uses decoration_on_invalid: ignore to gracefully handle missing Validator service
    $services->set('regex_parser.traceable_validator', TraceableValidator::class)
        ->decorate(ValidatorInterface::class, null, 10, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->arg('$validator', service('.inner'))
        ->arg('$collector', service('regex_parser.collector'));
};
