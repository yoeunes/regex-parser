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
 * Loads and manages configuration for the RegexParser bundle.
 *
 * Purpose: This class is the main entry point for the bundle's integration with
 * Symfony's Dependency Injection (DI) container. Its `load` method is called by
 * the Symfony kernel during the container build process. It is responsible for:
 * 1. Processing the user-defined configuration from `config/packages/regex_parser.yaml`.
 * 2. Loading the necessary service definitions from the `Resources/config` directory.
 * 3. Setting parameters in the DI container based on the processed configuration.
 * 4. Conditionally loading debug-only services (like the Web Profiler collector)
 *    based on the application's environment and configuration.
 */
class RegexParserExtension extends Extension
{
    /**
     * Loads the bundle's services and processes its configuration.
     *
     * Purpose: This method orchestrates the entire setup of the bundle within the
     * Symfony DI container. It reads the bundle's configuration, sets container
     * parameters, and loads the appropriate service definition files. As a contributor,
     * this is where you would manage the registration of new services or handle new
     * configuration options.
     *
     * @param array<array<string, mixed>> $configs   an array of configuration values from the application's config files
     * @param ContainerBuilder            $container the DI container builder instance
     *
     * @throws \Exception if the service definition files cannot be loaded
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
         *     cache: string|null,
         *     analysis: array{
         *         warning_threshold: int,
         *         redos_threshold: int,
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
        $container->setParameter('regex_parser.cache', $config['cache']);
        $container->setParameter('regex_parser.analysis.warning_threshold', $config['analysis']['warning_threshold']);
        $container->setParameter('regex_parser.analysis.redos_threshold', $config['analysis']['redos_threshold']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.php');
    }

    /**
     * Returns the alias of the extension.
     *
     * Purpose: This alias is used as the root key for the bundle's configuration
     * in YAML files (e.g., `regex_parser:`). It provides a unique namespace for the
     * bundle's settings.
     *
     * @return string the configuration alias
     */
    #[\Override]
    public function getAlias(): string
    {
        return 'regex_parser';
    }
}
