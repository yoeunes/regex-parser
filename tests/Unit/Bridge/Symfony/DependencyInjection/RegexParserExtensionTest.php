<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Bridge\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Analyzer\ValidatorRegexAnalyzer;
use RegexParser\Bridge\Symfony\CacheWarmer\RegexParserCacheWarmer;
use RegexParser\Bridge\Symfony\Command\RegexParserValidateCommand;
use RegexParser\Bridge\Symfony\DependencyInjection\RegexParserExtension;
use RegexParser\Regex;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegexParserExtensionTest extends TestCase
{
    public function testLoadSetsParametersAndServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $extension = new RegexParserExtension();
        $extension->load([[
            'enabled' => true,
            'max_pattern_length' => 42,
            'cache' => '/tmp/cache',
            'analysis' => [
                'warning_threshold' => 1,
                'redos_threshold' => 2,
                'ignore_patterns' => ['foo'],
            ],
        ]], $container);

        self::assertSame(42, $container->getParameter('regex_parser.max_pattern_length'));
        self::assertSame('/tmp/cache', $container->getParameter('regex_parser.cache'));
        self::assertSame(1, $container->getParameter('regex_parser.analysis.warning_threshold'));
        self::assertSame(2, $container->getParameter('regex_parser.analysis.redos_threshold'));
        self::assertSame(['foo'], $container->getParameter('regex_parser.analysis.ignore_patterns'));

        self::assertTrue($container->hasDefinition('regex_parser.regex'));
        self::assertTrue($container->hasDefinition(RouteRequirementAnalyzer::class));
        self::assertTrue($container->hasDefinition(ValidatorRegexAnalyzer::class));
        self::assertTrue($container->hasDefinition('regex_parser.cache_warmer'));
        self::assertTrue($container->hasDefinition('regex_parser.command.validate'));
    }

    public function testLoadSkipsWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);

        $extension = new RegexParserExtension();
        $extension->load([['enabled' => false]], $container);

        self::assertFalse($container->hasParameter('regex_parser.max_pattern_length'));
        self::assertFalse($container->hasDefinition('regex_parser.regex'));
        self::assertFalse($container->hasDefinition(RouteRequirementAnalyzer::class));
    }
}
