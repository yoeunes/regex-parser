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

namespace RegexParser\Bridge\Symfony\Extractor;

use RegexParser\Bridge\Symfony\Extractor\Strategy\ExtractionStrategyInterface;
use RegexParser\Bridge\Symfony\Extractor\Strategy\PhpStanExtractionStrategy;
use RegexParser\Bridge\Symfony\Extractor\Strategy\TokenBasedExtractionStrategy;

/**
 * Extracts constant regex patterns from PHP source files using best available strategy.
 *
 * This class uses a strategy pattern to choose between PHPStan-based extraction
 * (when available) and a token-based fallback approach.
 *
 * @internal
 */
final class RegexPatternExtractor
{
    /** @var list<ExtractionStrategyInterface> */
    private array $strategies;

    /**
     * @param list<ExtractionStrategyInterface> $strategies
     */
    public function __construct(array $strategies = []) {
        $this->strategies = empty($strategies) ? $this->createDefaultStrategies() : $strategies;

        // Sort strategies by priority (highest first)
        usort($this->strategies, static fn (ExtractionStrategyInterface $a, ExtractionStrategyInterface $b) => 
            $b->getPriority() <=> $a->getPriority()
        );
    }

    /**
     * @param list<string> $paths
     *
     * @return list<RegexPatternOccurrence>
     */
    public function extract(array $paths): array
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->isAvailable()) {
                return $strategy->extract($paths);
            }
        }

        // This should never happen as TokenBasedExtractionStrategy is always available
        throw new \RuntimeException('No extraction strategy is available');
    }

    /**
     * @return list<ExtractionStrategyInterface>
     */
    private function createDefaultStrategies(): array
    {
        return [
            new PhpStanExtractionStrategy(),
            new TokenBasedExtractionStrategy(),
        ];
    }
}