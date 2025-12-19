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

namespace RegexParser\Tests\Unit\Bridge\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\DependencyInjection\RegexParserExtension;
use RegexParser\Bridge\Symfony\Extractor\ExtractorInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegexParserExtensionTest extends TestCase
{
    public function test_load_sets_parameters_and_services(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $extension = new RegexParserExtension();
        $extension->load([[
            'max_pattern_length' => 42,
            'cache' => [
                'directory' => '/tmp/cache',
            ],
            'analysis' => [
                'warning_threshold' => 1,
                'redos_threshold' => 2,
                'ignore_patterns' => ['foo'],
            ],
        ]], $container);

        $this->assertSame(42, $container->getParameter('regex_parser.max_pattern_length'));
        $cacheConfig = (array) $container->getParameter('regex_parser.cache');
        $this->assertSame('/tmp/cache', $cacheConfig['directory']);
        $this->assertSame(1, $container->getParameter('regex_parser.analysis.warning_threshold'));
        $this->assertSame(2, $container->getParameter('regex_parser.analysis.redos_threshold'));
        $this->assertSame(['foo'], $container->getParameter('regex_parser.analysis.ignore_patterns'));

        $this->assertTrue($container->hasDefinition('regex_parser.regex'));
        $this->assertTrue($container->hasDefinition('regex_parser.extractor'));
        $this->assertTrue($container->hasDefinition('regex_parser.command.lint'));
    }

    public function test_load_sets_custom_extractor_alias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $extension = new RegexParserExtension();
        $extension->load([[
            'extractor_service' => 'my_custom_extractor',
        ]], $container);

        $this->assertTrue($container->hasAlias(ExtractorInterface::class));
        $this->assertSame('my_custom_extractor', (string) $container->getAlias(ExtractorInterface::class));
    }
}
