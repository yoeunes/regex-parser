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
    }

    public function analyze(string $regex, ?ReDoSSeverity $threshold = null): ReDoSAnalysis
    {
        $threshold ??= $this->threshold;

        if ($this->shouldIgnore($regex)) {
            return new ReDoSAnalysis(ReDoSSeverity::SAFE, 0, null, []);
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
                $result['recommendations'],
                null,
            );
        } catch (\Throwable $e) {
            return new ReDoSAnalysis(
                ReDoSSeverity::UNKNOWN,
                0,
                null,
                ['Analysis incomplete: '.$e->getMessage()],
                $e::class.': '.$e->getMessage(),
            );
        }
    }

    private function shouldIgnore(string $regex): bool
    {
        if ([] === $this->ignoredPatterns) {
            return false;
        }

        $normalized = $this->normalizePattern($regex);

        return \in_array($normalized, $this->ignoredPatterns, true) || \in_array($regex, $this->ignoredPatterns, true);
    }

    private function normalizePattern(string $regex): string
    {
        $pattern = $regex;
        $length = \strlen($pattern);

        if ($length >= 2) {
            $first = $pattern[0];
            $last = $pattern[$length - 1];

            if ($first === $last && \in_array($first, ['/', '#', '~', '%'], true)) {
                $pattern = substr($pattern, 1, -1);
            }
        }

        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }
}
