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
     * @return TreeBuilder the tree builder instance
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('regex_parser');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('max_pattern_length')
                    ->defaultValue(Regex::DEFAULT_MAX_PATTERN_LENGTH)
                    ->info('The maximum allowed length for a regex pattern string to parse.')
                ->end()
                ->integerNode('max_lookbehind_length')
                    ->defaultValue(Regex::DEFAULT_MAX_LOOKBEHIND_LENGTH)
                    ->min(0)
                    ->info('The maximum allowed lookbehind length. Can be overridden per-pattern via (*LIMIT_LOOKBEHIND=...).')
                ->end()
                ->scalarNode('cache')
                    ->defaultNull()
                    ->info('Directory path for cached AST files. Set to null to disable caching.')
                ->end()
                ->scalarNode('cache_pool')
                    ->defaultNull()
                    ->info('Symfony cache pool service id (PSR-6). Takes precedence over "cache" when set.')
                ->end()
                ->scalarNode('cache_prefix')
                    ->defaultValue('regex_')
                    ->info('Cache key prefix for PSR-6 cache pools.')
                ->end()
                ->scalarNode('extractor_service')
                    ->defaultNull()
                    ->info('Custom regex pattern extractor service ID. If not provided, PhpStan will be tried first, then token-based extraction.')
                ->end()
                ->arrayNode('redos')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('threshold')
                            ->defaultValue('high')
                            ->info('Minimum ReDoS severity to report (safe|low|medium|high|critical).')
                            ->beforeNormalization()
                                ->ifString()
                                ->then(static fn (string $value): string => strtolower($value))
                            ->end()
                            ->validate()
                                ->ifNotInArray(['safe', 'low', 'medium', 'high', 'critical'])
                                ->thenInvalid('Invalid "regex_parser.redos.threshold" value "%s". Allowed: safe, low, medium, high, critical.')
                            ->end()
                        ->end()
                        ->arrayNode('ignored_patterns')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                            ->info('List of patterns or full regexes to exclude from ReDoS analysis.')
                        ->end()
                    ->end()
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
                 ->arrayNode('paths')
                     ->scalarPrototype()->end()
                     ->defaultValue(['src'])
                     ->info('Directories to scan for regex patterns. Defaults to src/ for Symfony applications.')
                 ->end()
                 ->arrayNode('exclude_paths')
                 ->scalarPrototype()->end()
                 ->defaultValue(['vendor'])
                 ->info('Directories to exclude from scanning. Defaults to vendor/ for Symfony applications.')
                 ->end()
                 ->scalarNode('editor')
                     ->defaultNull()
                     ->info('Shorthand for common editors (code/vscode, phpstorm, sublime). Use "editor_url" for custom templates.')
                     ->beforeNormalization()
                         ->ifString()
                         ->then(static fn (string $value): string => strtolower($value))
                     ->end()
                     ->validate()
                         ->ifTrue(static fn ($value): bool => null !== $value && !\in_array($value, ['code', 'vscode', 'phpstorm', 'storm', 'idea', 'sublime', 'subl'], true))
                         ->thenInvalid('Invalid "regex_parser.editor" value "%s". Allowed: code, vscode, phpstorm, storm, idea, sublime, subl.')
                     ->end()
                 ->end()
                 ->scalarNode('editor_url')
                     ->defaultNull()
                     ->info('Editor URL template for clickable links (e.g., phpstorm://open?file=%%file%%&line=%%line%%&column=%%column%%). Falls back to framework.ide.')
                 ->end()
            ->end();

        return $treeBuilder;
    }
}
