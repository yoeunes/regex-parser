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
 * Configuration for the RegexParser bundle.
 *
 * @see https://symfony.com/doc/current/bundles/configuration.html
 */
final readonly class Configuration implements ConfigurationInterface
{
    public function __construct(
        private bool $debug = false,
    ) {}

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
