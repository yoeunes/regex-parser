<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\DependencyInjection\Configuration;
use RegexParser\Regex;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration(false);

        $config = $processor->processConfiguration($configuration, []);

        self::assertFalse($config['enabled']);
        self::assertSame(Regex::DEFAULT_MAX_PATTERN_LENGTH, $config['max_pattern_length']);
        self::assertNull($config['cache']);
        self::assertSame(50, $config['analysis']['warning_threshold']);
        self::assertSame(100, $config['analysis']['redos_threshold']);
        self::assertSame([], $config['analysis']['ignore_patterns']);
    }

    public function testCustomConfiguration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $config = $processor->processConfiguration($configuration, [[
            'enabled' => true,
            'max_pattern_length' => 10,
            'cache' => '/tmp/cache',
            'analysis' => [
                'warning_threshold' => 1,
                'redos_threshold' => 2,
                'ignore_patterns' => ['foo', 'bar'],
            ],
        ]]);

        self::assertTrue($config['enabled']);
        self::assertSame(10, $config['max_pattern_length']);
        self::assertSame('/tmp/cache', $config['cache']);
        self::assertSame(['foo', 'bar'], $config['analysis']['ignore_patterns']);
        self::assertSame(1, $config['analysis']['warning_threshold']);
        self::assertSame(2, $config['analysis']['redos_threshold']);
    }
}
