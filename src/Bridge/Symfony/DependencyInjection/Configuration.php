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

use RegexParser\Regex;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the configuration schema for the RegexParser bundle.
 */
final readonly class Configuration implements ConfigurationInterface
{
    /**
     * @param bool $debug The value of the `kernel.debug` parameter.
     */
    public function __construct(
        private bool $debug = false,
    ) {}

    /**
     * @return TreeBuilder the tree builder instance
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('regex_parser');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->defaultValue($this->debug)
                    ->info('Enable or disable the RegexParser bundle entirely. Defaults to dev/test only.')
                ->end()
                ->integerNode('max_pattern_length')
                    ->defaultValue(Regex::DEFAULT_MAX_PATTERN_LENGTH)
                    ->info('The maximum allowed length for a regex pattern string to parse.')
                ->end()
                ->scalarNode('cache')
                    ->defaultNull()
                    ->info('Directory path for cached AST files. Set to null to disable caching.')
                ->end()
                ->arrayNode('analysis')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('warning_threshold')
                            ->defaultValue(50)
                            ->min(0)
                            ->info('Complexity score above which a warning is emitted.')
                        ->end()
                        ->integerNode('redos_threshold')
                            ->defaultValue(100)
                            ->min(0)
                            ->info('Complexity score above which a pattern is flagged as ReDoS risk.')
                        ->end()
                        ->arrayNode('ignore_patterns')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                            ->info('List of regex fragments to treat as safe (e.g. Symfony requirement constants).')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
