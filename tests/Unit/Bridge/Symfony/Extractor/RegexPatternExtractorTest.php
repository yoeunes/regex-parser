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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Extractor;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Extractor\ExtractorInterface;
use RegexParser\Bridge\Symfony\Extractor\PhpStanExtractionStrategy;
use RegexParser\Bridge\Symfony\Extractor\RegexPatternExtractor;
use RegexParser\Bridge\Symfony\Extractor\TokenBasedExtractionStrategy;

final class RegexPatternExtractorTest extends TestCase
{
    public function test_delegates_to_injected_extractor(): void
    {
        $mockExtractor = $this->createMock(ExtractorInterface::class);
        $mockExtractor->method('extract')->willReturn(['pattern1', 'pattern2']);

        $extractor = new RegexPatternExtractor($mockExtractor);

        $result = $extractor->extract(['test.php']);

        $this->assertSame(['pattern1', 'pattern2'], $result);
    }

    public function test_works_with_phpstan_extractor(): void
    {
        $phpstanExtractor = new PhpStanExtractionStrategy();

        $extractor = new RegexPatternExtractor($phpstanExtractor);

        $result = $extractor->extract(['nonexistent']);

        $this->assertIsArray($result);
    }

    public function test_works_with_token_based_extractor(): void
    {
        $tokenExtractor = new TokenBasedExtractionStrategy();

        $extractor = new RegexPatternExtractor($tokenExtractor);

        $result = $extractor->extract(['nonexistent']);

        $this->assertIsArray($result);
    }
}