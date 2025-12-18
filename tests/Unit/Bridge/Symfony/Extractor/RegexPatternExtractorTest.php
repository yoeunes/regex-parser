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
use RegexParser\Bridge\Symfony\Extractor\RegexPatternExtractor;
use RegexParser\Bridge\Symfony\Extractor\Strategy\ExtractionStrategyInterface;
use RegexParser\Bridge\Symfony\Extractor\Strategy\PhpStanExtractionStrategy;
use RegexParser\Bridge\Symfony\Extractor\Strategy\TokenBasedExtractionStrategy;

final class RegexPatternExtractorTest extends TestCase
{
    public function test_uses_token_based_strategy_when_phpstan_not_available(): void
    {
        $mockStrategy = $this->createMock(ExtractionStrategyInterface::class);
        $mockStrategy->method('isAvailable')->willReturn(true);
        $mockStrategy->method('getPriority')->willReturn(1);
        $mockStrategy->method('extract')->willReturn([]);

        $extractor = new RegexPatternExtractor([$mockStrategy]);
        
        $result = $extractor->extract(['nonexistent']);
        
        $this->assertIsArray($result);
    }

    public function test_prefers_higher_priority_strategy(): void
    {
        $lowPriorityStrategy = $this->createMock(ExtractionStrategyInterface::class);
        $lowPriorityStrategy->method('isAvailable')->willReturn(true);
        $lowPriorityStrategy->method('getPriority')->willReturn(1);
        $lowPriorityStrategy->expects($this->never())->method('extract');

        $highPriorityStrategy = $this->createMock(ExtractionStrategyInterface::class);
        $highPriorityStrategy->method('isAvailable')->willReturn(true);
        $highPriorityStrategy->method('getPriority')->willReturn(10);
        $highPriorityStrategy->method('extract')->willReturn([]);

        $extractor = new RegexPatternExtractor([$lowPriorityStrategy, $highPriorityStrategy]);
        
        $extractor->extract(['nonexistent']);
    }

    public function test_fallbacks_to_next_strategy_when_first_not_available(): void
    {
        $unavailableStrategy = $this->createMock(ExtractionStrategyInterface::class);
        $unavailableStrategy->method('isAvailable')->willReturn(false);
        $unavailableStrategy->method('getPriority')->willReturn(10);
        $unavailableStrategy->expects($this->never())->method('extract');

        $availableStrategy = $this->createMock(ExtractionStrategyInterface::class);
        $availableStrategy->method('isAvailable')->willReturn(true);
        $availableStrategy->method('getPriority')->willReturn(1);
        $availableStrategy->method('extract')->willReturn([]);

        $extractor = new RegexPatternExtractor([$unavailableStrategy, $availableStrategy]);
        
        $result = $extractor->extract(['nonexistent']);
        
        $this->assertIsArray($result);
    }

    public function test_token_based_strategy_is_always_available(): void
    {
        $strategy = new TokenBasedExtractionStrategy();
        
        $this->assertTrue($strategy->isAvailable());
        $this->assertSame(1, $strategy->getPriority());
    }

    public function test_phpstan_strategy_checks_availability(): void
    {
        $strategy = new PhpStanExtractionStrategy();
        
        // Should not throw exception, just return boolean
        $isAvailable = $strategy->isAvailable();
        $this->assertIsBool($isAvailable);
        
        $this->assertSame(10, $strategy->getPriority());
    }

    public function test_creates_default_strategies_when_none_provided(): void
    {
        $extractor = new RegexPatternExtractor();
        
        // Should work without exceptions
        $result = $extractor->extract(['nonexistent']);
        $this->assertIsArray($result);
    }
}