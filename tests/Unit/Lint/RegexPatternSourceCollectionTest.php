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

namespace RegexParser\Tests\Unit\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\RegexPatternSourceCollection;
use RegexParser\Lint\RegexPatternSourceContext;

final class RegexPatternSourceCollectionTest extends TestCase
{
    public function test_construct(): void
    {
        $sources = [];
        $collection = new RegexPatternSourceCollection($sources);
        $this->assertInstanceOf(RegexPatternSourceCollection::class, $collection);
    }

    public function test_collect_with_empty_sources(): void
    {
        $collection = new RegexPatternSourceCollection([]);
        $context = new RegexPatternSourceContext([], []);
        $result = $collection->collect($context);
        $this->assertSame([], $result);
    }

    public function test_collect_filters_disabled_sources(): void
    {
        $source = $this->createMock(\RegexParser\Lint\RegexPatternSourceInterface::class);
        $source->method('getName')->willReturn('test');
        $source->method('isSupported')->willReturn(true);
        $source->method('extract')->willReturn([]);

        $context = new RegexPatternSourceContext([], [], ['test']);

        $collection = new RegexPatternSourceCollection([$source]);
        $result = $collection->collect($context);
        $this->assertSame([], $result);
    }

    public function test_collect_filters_unsupported_sources(): void
    {
        $source = $this->createMock(\RegexParser\Lint\RegexPatternSourceInterface::class);
        $source->method('getName')->willReturn('test');
        $source->method('isSupported')->willReturn(false);

        $context = new RegexPatternSourceContext([], []);

        $collection = new RegexPatternSourceCollection([$source]);
        $result = $collection->collect($context);
        $this->assertSame([], $result);
    }

    public function test_collect_aggregates_patterns(): void
    {
        $source1 = $this->createMock(\RegexParser\Lint\RegexPatternSourceInterface::class);
        $source1->method('getName')->willReturn('test1');
        $source1->method('isSupported')->willReturn(true);
        $source1->method('extract')->willReturn(['pattern1']);

        $source2 = $this->createMock(\RegexParser\Lint\RegexPatternSourceInterface::class);
        $source2->method('getName')->willReturn('test2');
        $source2->method('isSupported')->willReturn(true);
        $source2->method('extract')->willReturn(['pattern2']);

        $context = new RegexPatternSourceContext([], []);

        $collection = new RegexPatternSourceCollection([$source1, $source2]);
        $result = $collection->collect($context);
        $this->assertSame(['pattern1', 'pattern2'], $result);
    }
}
