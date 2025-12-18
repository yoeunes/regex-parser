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

use RegexParser\Cache\FilesystemCache;
use RegexParser\Cache\NullCache;
use RegexParser\Cache\PsrCacheAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads and manages configuration for the RegexParser bundle.
 */
final class RegexParserExtension extends Extension
{
    /**
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
         *     max_lookbehind_length: int,
         *     cache: string|null,
         *     cache_pool: string|null,
         *     cache_prefix: string,
         *     redos: array{
         *         threshold: string,
         *         ignored_patterns: array<int, string>,
         *     },
         *     analysis: array{
         *         warning_threshold: int,
         *         redos_threshold: int,
         *         ignore_patterns: array<int, string>,
         *     },
         *     editor_url: string|null,
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);

        // If the bundle is disabled entirely, do nothing
        if (!$config['enabled']) {
            return;
        }

        $ignoredPatterns = array_values(array_unique([
            ...$config['analysis']['ignore_patterns'],
            ...$config['redos']['ignored_patterns'],
        ]));

        // Resolve editor URL with fallbacks
        $editorUrl = $config['editor_url'];
        
        // Fallback to framework.ide if regex_parser.editor_url is not set
        if (null === $editorUrl && $container->hasParameter('framework.ide')) {
            $ide = $container->getParameter('framework.ide');
            if (null !== $ide && '' !== $ide) {
                // Convert Symfony format (%%f%%, %%l%%) to our format (%%file%%, %%line%%)
                $editorUrl = str_replace(['%%f%%', '%%l%%'], ['%%file%%', '%%line%%'], $ide);
            }
        }
        
        // Fallback to PHPStan editorUrl if still not set
        if (null === $editorUrl) {
            $phpstanEditorUrl = $this->findPhpstanEditorUrl();
            if (null !== $phpstanEditorUrl) {
                $editorUrl = $phpstanEditorUrl;
            }
        }

        // Set parameters
        $container->setParameter('regex_parser.max_pattern_length', $config['max_pattern_length']);
        $container->setParameter('regex_parser.max_lookbehind_length', $config['max_lookbehind_length']);
        $container->setParameter('regex_parser.cache', $config['cache']);
        $container->setParameter('regex_parser.cache_pool', $config['cache_pool']);
        $container->setParameter('regex_parser.cache_prefix', $config['cache_prefix']);
        $container->setParameter('regex_parser.redos.threshold', $config['redos']['threshold']);
        $container->setParameter('regex_parser.redos.ignored_patterns', $ignoredPatterns);
        $container->setParameter('regex_parser.analysis.warning_threshold', $config['analysis']['warning_threshold']);
        $container->setParameter('regex_parser.analysis.redos_threshold', $config['analysis']['redos_threshold']);
        $container->setParameter('regex_parser.analysis.ignore_patterns', $ignoredPatterns);
        $container->setParameter('regex_parser.editor_url', $editorUrl);

        $container->setDefinition('regex_parser.cache', $this->buildCacheDefinition($config));

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.php');
    }

    /**
     * @return string the configuration alias
     */
    #[\Override]
    public function getAlias(): string
    {
        return 'regex_parser';
    }

    /**
     * @param array{
     *     cache: string|null,
     *     cache_pool: string|null,
     *     cache_prefix: string,
     * } $config
     */
    private function buildCacheDefinition(array $config): Definition
    {
        if (null !== $config['cache_pool'] && '' !== $config['cache_pool']) {
            return (new Definition(PsrCacheAdapter::class))
                ->setArguments([
                    new Reference((string) $config['cache_pool']),
                    (string) $config['cache_prefix'],
                ]);
        }

        if (null !== $config['cache'] && '' !== $config['cache']) {
            return (new Definition(FilesystemCache::class))
                ->setArguments([(string) $config['cache']]);
        }

        return new Definition(NullCache::class);
    }

    /**
     * Try to find editor URL from PHPStan configuration files.
     */
    private function findPhpstanEditorUrl(): ?string
    {
        $possibleConfigs = [
            'phpstan.neon',
            'phpstan.dist.neon', 
            'phpstan.neon.dist'
        ];

        $cwd = getcwd();
        if (false === $cwd) {
            return null;
        }

        foreach ($possibleConfigs as $config) {
            $configPath = $cwd . '/' . $config;
            if (file_exists($configPath)) {
                $content = file_get_contents($configPath);
                if (false !== $content) {
                    // Simple NEON parsing (basic key-value pairs)
                    $lines = explode("\n", $content);
                    $currentSection = null;
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ('' === $line || str_starts_with($line, '#')) {
                            continue;
                        }
                        
                        if (str_ends_with($line, ':')) {
                            $currentSection = substr($line, 0, -1);
                            continue;
                        }
                        
                        if ('parameters' === $currentSection && str_contains($line, 'editorUrl:')) {
                            [, $value] = explode(':', $line, 2);
                            return trim($value, ' "\'');
                        }
                    }
                }
            }
        }

        return null;
    }
}
