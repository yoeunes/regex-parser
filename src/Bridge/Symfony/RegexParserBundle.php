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

namespace RegexParser\Bridge\Symfony;

use RegexParser\Bridge\Symfony\Service\TraceableRouter;
use RegexParser\Bridge\Symfony\Service\TraceableValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegexParserBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Decorate the router and validator only in debug mode
        if ($container->getParameter('kernel.debug')) {
            $this->decorateRouter($container);
            $this->decorateValidator($container);
        }
    }

    private function decorateRouter(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('router.default')) {
            return;
        }

        $decorator = new Definition(TraceableRouter::class, [
            new Reference('regex_parser.router.inner'),
            new Reference('regex_parser.collector'),
        ]);
        $decorator->setDecoratedService(RouterInterface::class, 'regex_parser.router', 10);
        $decorator->setAutowired(true);
        $decorator->setAutoconfigured(true);

        $container->setDefinition('regex_parser.router', $decorator);
    }

    private function decorateValidator(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('validator')) {
            return;
        }

        $decorator = new Definition(TraceableValidator::class, [
            new Reference('regex_parser.validator.inner'),
            new Reference('regex_parser.collector'),
        ]);
        $decorator->setDecoratedService(ValidatorInterface::class, 'regex_parser.validator', 10);
        $decorator->setAutowired(true);
        $decorator->setAutoconfigured(true);

        $container->setDefinition('regex_parser.validator', $decorator);
    }
}
