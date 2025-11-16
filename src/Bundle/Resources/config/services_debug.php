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

use RegexParser\Bundle\DataCollector\RegexCollector;
use RegexParser\Bundle\Service\TraceableRouter;
use RegexParser\Bundle\Service\TraceableValidator;
use RegexParser\NodeVisitor\ComplexityScoreVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->publicFalse();

    $services->set('regex_parser.collector', RegexCollector::class)
        ->arg('$explainVisitor', service(ExplainVisitor::class))
        ->arg('$scoreVisitor', service(ComplexityScoreVisitor::class))
        ->tag('data_collector', [
            'template' => '@RegexParser/Profiler/regex_panel.html.twig',
            'id' => 'regex',
            'priority' => 270,
        ]);

    $services->set('regex_parser.router', TraceableRouter::class)
        ->decorate(RouterInterface::class, null, 10) // priority 10
        ->arg('$router', service('.inner')) // .inner is the magic key for the decorated service
        ->arg('$collector', service('regex_parser.collector'));

    $services->set('regex_parser.validator', TraceableValidator::class)
        ->decorate(ValidatorInterface::class, null, 10)
        ->arg('$validator', service('.inner'))
        ->arg('$collector', service('regex_parser.collector'));
};
