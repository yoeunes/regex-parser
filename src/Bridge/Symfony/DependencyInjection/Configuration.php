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

use RegexParser\Parser;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the configuration schema for the RegexParser bundle.
 *
 * Purpose: This class is responsible for defining and validating the configuration
 * options that users can set in their `config/packages/regex_parser.yaml` file.
 * It uses Symfony's Config component to create a structured tree of options, set
 * default values, and provide documentation for each setting. This ensures that
 * the bundle is configured in a predictable and reliable way.
 *
 * @see https://symfony.com/doc/current/bundles/configuration.html
 */
class Configuration implements ConfigurationInterface
{
    /**
     * Creates a new instance of the bundle's configuration schema.
     *
     * Purpose: This constructor receives the kernel's debug status, which is used to
     * set a sensible default for the profiler. The profiler is a debugging tool and
     * should typically only be enabled in a debug environment to avoid performance
     * overhead in production.
     *
     * @param bool $debug The value of the `kernel.debug` parameter.
     */
    public function __construct(
        private readonly bool $debug = false,
    ) {}

    /**
     * Generates the configuration tree builder.
     *
     * Purpose: This method defines the structure, default values, and documentation for
     * all the configuration settings supported by the bundle. As a contributor, if you
     * need to add a new configuration option, you would add it to the `TreeBuilder`
     * in this method. This is the single source of truth for the bundle's configuration schema.
     *
     * @return TreeBuilder the tree builder instance
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('regex_parser');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable or disable the RegexParser bundle entirely.')
                ->end()
                ->integerNode('max_pattern_length')
                    ->defaultValue(Parser::DEFAULT_MAX_PATTERN_LENGTH)
                    ->info('The maximum allowed length for a regex pattern string to parse.')
                ->end()
                ->arrayNode('profiler')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultValue($this->debug)
                            ->info('Enable the profiler data collector. Defaults to the value of kernel.debug.')
                        ->end()
                        ->integerNode('redos_threshold')
                            ->defaultValue(100)
                            ->info('Complexity score threshold above which a pattern is flagged as ReDoS risk.')
                        ->end()
                        ->integerNode('warning_threshold')
                            ->defaultValue(50)
                            ->info('Complexity score threshold above which a pattern is flagged as warning.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
