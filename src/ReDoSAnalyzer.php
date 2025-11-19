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

namespace RegexParser;

use RegexParser\NodeVisitor\ReDoSProfileVisitor;

final readonly class ReDoSAnalyzer
{
    public function __construct(private ?Parser $parser = new Parser()) {}

    /**
     * Analyzes a regex pattern for ReDoS vulnerabilities and returns a detailed report.
     */
    public function analyze(string $pattern): ReDoSAnalysis
    {
        try {
            $ast = $this->parser->parse($pattern);
            $visitor = new ReDoSProfileVisitor();
            $ast->accept($visitor);

            $result = $visitor->getResult();

            return new ReDoSAnalysis(
                $result['severity'],
                $this->calculateScore($result['severity']),
                $result['vulnerablePattern'],
                $result['recommendations'],
            );
        } catch (\Throwable $e) {
            // Fallback for parsing errors, treat as unknown/safe or rethrow
            // Here we return a basic SAFE analysis to avoid breaking flows,
            // but ideally the user should validate() first.
            return new ReDoSAnalysis(ReDoSSeverity::SAFE, 0, null, ['Error parsing regex: '.$e->getMessage()]);
        }
    }

    private function calculateScore(ReDoSSeverity $severity): int
    {
        return match ($severity) {
            ReDoSSeverity::SAFE => 0,
            ReDoSSeverity::LOW => 2,
            ReDoSSeverity::MEDIUM => 5,
            ReDoSSeverity::HIGH => 8,
            ReDoSSeverity::CRITICAL => 10,
        };
    }
}
