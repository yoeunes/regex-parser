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

namespace RegexParser\ReDoS;

use RegexParser\NodeVisitor\ReDoSProfileNodeVisitor;
use RegexParser\Regex;

final class ReDoSAnalyzer
{
    /**
     * @var list<string>
     */
    private array $ignoredPatternsNormalized = [];

    /**
     * @param list<string> $ignoredPatterns
     */
    public function __construct(
        private readonly ?Regex $regex = null,
        /**
         * @var list<string>
         */
        private array $ignoredPatterns = [],
        private readonly ReDoSSeverity $threshold = ReDoSSeverity::HIGH,
    ) {
        $this->ignoredPatterns = array_values(array_unique($this->ignoredPatterns));
        $this->ignoredPatternsNormalized = $this->normalizeIgnoredPatterns($this->ignoredPatterns);
    }

    public function analyze(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis
    {
        $threshold ??= $this->threshold;

        if ($this->shouldIgnore($regex)) {
            return new ReDoSAnalysis(
                ReDoSSeverity::SAFE,
                0,
                null,
                [],
                null,
                null,
                null,
                ReDoSConfidence::LOW,
                null,
                [],
            );
        }

        try {
            $ast = ($this->regex ?? Regex::create())->parse($regex);
            $visitor = new ReDoSProfileNodeVisitor();
            $ast->accept($visitor);

            $result = $visitor->getResult();

            return new ReDoSAnalysis(
                $result['severity'],
                match ($result['severity']) {
                    ReDoSSeverity::SAFE => 0,
                    ReDoSSeverity::LOW => 2,
                    ReDoSSeverity::MEDIUM => 5,
                    ReDoSSeverity::HIGH => 8,
                    ReDoSSeverity::CRITICAL => 10,
                    ReDoSSeverity::UNKNOWN => 5,
                },
                $result['vulnerablePattern'],
                array_values($result['recommendations']),
                null,
                $result['vulnerablePattern'],
                $result['trigger'],
                $result['confidence'],
                $result['falsePositiveRisk'],
                array_values($result['findings']),
            );
        } catch (\Throwable $e) {
            return new ReDoSAnalysis(
                ReDoSSeverity::UNKNOWN,
                0,
                null,
                ['Analysis incomplete: '.$e->getMessage()],
                $e::class.': '.$e->getMessage(),
                null,
                null,
                ReDoSConfidence::LOW,
                null,
                [],
            );
        }
    }

    private function shouldIgnore(string $regex): bool
    {
        if ([] === $this->ignoredPatterns) {
            return false;
        }

        $normalized = $this->normalizePattern($regex);

        return \in_array($normalized, $this->ignoredPatternsNormalized, true)
            || \in_array($normalized, $this->ignoredPatterns, true)
            || \in_array($regex, $this->ignoredPatterns, true);
    }

    private function normalizePattern(string $regex): string
    {
        try {
            [$pattern] = ($this->regex ?? Regex::create())->extractPatternAndFlags($regex);

            return $pattern;
        } catch (\Throwable) {
            return $regex;
        }
    }

    /**
     * @param list<string> $patterns
     *
     * @return list<string>
     */
    private function normalizeIgnoredPatterns(array $patterns): array
    {
        $normalized = [];
        foreach ($patterns as $pattern) {
            $normalized[] = $this->normalizePattern($pattern);
        }

        return array_values(array_unique($normalized));
    }
}
