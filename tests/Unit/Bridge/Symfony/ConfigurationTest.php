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
        $configuration = new Configuration(false);

        $config = $processor->processConfiguration($configuration, []);

        $this->assertFalse($config['enabled']);
        $this->assertSame(Regex::DEFAULT_MAX_PATTERN_LENGTH, $config['max_pattern_length']);
        $this->assertNull($config['cache']);
        $this->assertSame(50, $config['analysis']['warning_threshold']);
        $this->assertSame(100, $config['analysis']['redos_threshold']);
        $this->assertSame([], $config['analysis']['ignore_patterns']);
    }

    public function test_custom_configuration(): void
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

        $this->assertTrue($config['enabled']);
        $this->assertSame(10, $config['max_pattern_length']);
        $this->assertSame('/tmp/cache', $config['cache']);
        $this->assertSame(['foo', 'bar'], $config['analysis']['ignore_patterns']);
        $this->assertSame(1, $config['analysis']['warning_threshold']);
        $this->assertSame(2, $config['analysis']['redos_threshold']);
    }
}
