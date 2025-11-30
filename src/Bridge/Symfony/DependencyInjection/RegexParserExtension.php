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

namespace RegexParser\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Extension for the RegexParser bundle.
 *
 * Loads base services and conditionally loads debug services (data collector,
 * traceable decorators) based on the `profiler.enabled` configuration option.
 */
class RegexParserExtension extends Extension
{
    /**
     * @param array<array<string, mixed>> $configs
     *
     * @throws \Exception
     */
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Pass kernel.debug to Configuration for default values
        $debug = (bool) $container->getParameter('kernel.debug');
        $configuration = new Configuration($debug);

        /**
         * @var array{
         *     enabled: bool,
         *     max_pattern_length: int,
         *     profiler: array{
         *         enabled: bool,
         *         redos_threshold: int,
         *         warning_threshold: int,
         *     },
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        // If the bundle is disabled entirely, do nothing
        if (!$config['enabled']) {
            return;
        }

        // Set parameters
        $container->setParameter('regex_parser.max_pattern_length', $config['max_pattern_length']);
        $container->setParameter('regex_parser.profiler.enabled', $config['profiler']['enabled']);
        $container->setParameter('regex_parser.profiler.redos_threshold', $config['profiler']['redos_threshold']);
        $container->setParameter('regex_parser.profiler.warning_threshold', $config['profiler']['warning_threshold']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        // Always load base parser services
        $loader->load('services.php');

        // Load debug services (collector, decorators) only if profiler is enabled
        if ($config['profiler']['enabled']) {
            $loader->load('services_debug.php');
        }
    }

    #[\Override]
    public function getAlias(): string
    {
        return 'regex_parser';
    }
}
