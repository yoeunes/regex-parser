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

namespace RegexParser\Tests\Unit\Bridge\Symfony;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\DependencyInjection\Configuration;
use RegexParser\Regex;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function test_default_configuration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        /**
         * @var array{
         *     max_pattern_length: int,
         *     cache: string|null,
         *     extractor_service: string|null,
         *     analysis: array{
         *         warning_threshold: int,
         *         redos_threshold: int,
         *         ignore_patterns: array<int, string>
         *     }
         * } $config
         */
        $config = $processor->processConfiguration($configuration, []);

        $this->assertSame(Regex::DEFAULT_MAX_PATTERN_LENGTH, $config['max_pattern_length']);
        $this->assertNull($config['cache']);
        $this->assertNull($config['extractor_service']);
        $this->assertSame(50, $config['analysis']['warning_threshold']);
        $this->assertSame(100, $config['analysis']['redos_threshold']);
        $this->assertSame([], $config['analysis']['ignore_patterns']);
    }

    public function test_custom_configuration(): void
    {
        $processor = new Processor();
        $configuration = new Configuration();

        /**
         * @var array{
         *     max_pattern_length: int,
         *     cache: string|null,
         *     extractor_service: string|null,
         *     analysis: array{
         *         warning_threshold: int,
         *         redos_threshold: int,
         *         ignore_patterns: array<int, string>
         *     }
         * } $config
         */
        $config = $processor->processConfiguration($configuration, [[
            'max_pattern_length' => 10,
            'cache' => '/tmp/cache',
            'extractor_service' => 'my_custom_extractor',
            'analysis' => [
                'warning_threshold' => 1,
                'redos_threshold' => 2,
                'ignore_patterns' => ['foo', 'bar'],
            ],
        ]]);

        /*
         * @var array{
         *     max_pattern_length: int,
         *     cache: string|null,
         *     extractor_service: string|null,
         *     analysis: array{
         *         warning_threshold: int,
         *         redos_threshold: int,
         *         ignore_patterns: array<int, string>
         *     }
         * } $config
         */

        $this->assertSame(10, $config['max_pattern_length']);
        $this->assertSame('/tmp/cache', $config['cache']);
        $this->assertSame('my_custom_extractor', $config['extractor_service'] ?? 'should_not_exist');
        $this->assertSame(['foo', 'bar'], $config['analysis']['ignore_patterns'] ?? []);
        $this->assertSame(1, $config['analysis']['warning_threshold'] ?? 0);
        $this->assertSame(2, $config['analysis']['redos_threshold'] ?? 'high');
    }
}
