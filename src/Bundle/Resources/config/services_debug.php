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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('regex_parser.collector', RegexCollector::class)
            ->arg('$regex', service('regex_parser.regex'))
            ->arg('$explainVisitor', service('regex_parser.visitor.explain'))
            ->arg('$scoreVisitor', service('regex_parser.visitor.complexity_score'))
            ->tag('data_collector', [
                'template' => '@RegexParser/Profiler/regex_panel.html.twig',
                'id' => 'regex',
                'priority' => 270,
            ])

        ->set('regex_parser.router.decorator', TraceableRouter::class)
            ->decorate(RouterInterface::class, null, 10)
            ->arg('$router', service('.inner'))
            ->arg('$collector', service('regex_parser.collector'))

        ->set('regex_parser.validator.decorator', TraceableValidator::class)
            ->decorate(ValidatorInterface::class, null, 10)
            ->arg('$validator', service('.inner'))
            ->arg('$collector', service('regex_parser.collector'));
};
