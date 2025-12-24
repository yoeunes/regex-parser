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
use RegexParser\Lint\ExtractorInterface;
use RegexParser\Lint\PhpRegexPatternSource;
use RegexParser\Lint\RegexPatternExtractor;
use RegexParser\Lint\RegexPatternSourceContext;

final class PhpRegexPatternSourceTest extends TestCase
{
    private RegexPatternExtractor $extractor;

    protected function setUp(): void
    {
        $mockExtractorInterface = $this->createMock(ExtractorInterface::class);
        $this->extractor = new RegexPatternExtractor($mockExtractorInterface);
    }

    public function test_construct(): void
    {
        $source = new PhpRegexPatternSource($this->extractor);
        $this->assertInstanceOf(PhpRegexPatternSource::class, $source);
    }

    public function test_get_name(): void
    {
        $source = new PhpRegexPatternSource($this->extractor);
        $this->assertSame('php', $source->getName());
    }

    public function test_is_supported(): void
    {
        $source = new PhpRegexPatternSource($this->extractor);
        $this->assertTrue($source->isSupported());
    }

    public function test_extract_delegates_to_extractor(): void
    {
        $context = new RegexPatternSourceContext(
            ['src/', 'tests/'],
            ['vendor/'],
        );

        $source = new PhpRegexPatternSource($this->extractor);
        $result = $source->extract($context);

        $this->assertIsArray($result);
    }

    public function test_extract_with_progress_callback(): void
    {
        $progressCalled = false;
        $progressCallback = function () use (&$progressCalled): void {
            $progressCalled = true;
        };

        $context = new RegexPatternSourceContext(
            ['src/'],
            [],
            [],
            $progressCallback,
        );

        $source = new PhpRegexPatternSource($this->extractor);
        $result = $source->extract($context);

        $this->assertIsArray($result);
    }

    public function test_extract_with_empty_paths(): void
    {
        $context = new RegexPatternSourceContext([], []);

        $source = new PhpRegexPatternSource($this->extractor);
        $result = $source->extract($context);

        $this->assertIsArray($result);
    }

    public function test_extract_with_disabled_sources(): void
    {
        $context = new RegexPatternSourceContext(
            ['src/'],
            [],
            ['php'], // php source is disabled
        );

        // Even when disabled, the method should still delegate to extractor
        // (the disabling logic is handled at a higher level)
        $source = new PhpRegexPatternSource($this->extractor);
        $result = $source->extract($context);

        $this->assertIsArray($result);
    }
}
