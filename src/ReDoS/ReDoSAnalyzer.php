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

class ReDoSAnalyzer
{
    /**
     * Analyzes a regex pattern for ReDoS vulnerabilities and returns a detailed report.
     *
     * Purpose: This is the main entry point for the ReDoS detection engine. It orchestrates
     * the analysis by first parsing the regex into an AST and then walking that tree with
     * the `ReDoSProfileNodeVisitor`. The visitor is responsible for identifying dangerous
     * patterns, such as "evil twins" (ambiguous, repeated quantifiers). This method then
     * compiles the visitor's findings into a structured `ReDoSAnalysis` report.
     *
     * @param string $regex the full PCRE regex string to be analyzed for vulnerabilities
     *
     * @return ReDoSAnalysis a comprehensive report object containing the severity of any
     *                       detected issues, a complexity score, the problematic part of
     *                       the pattern, and suggestions for how to fix it
     *
     * @example
     * ```php
     * $analyzer = new ReDoSAnalyzer();
     * $report = $analyzer->analyze('/(a|a)+/'); // This is a vulnerable pattern
     *
     * if ($report->severity === ReDoSSeverity::HIGH) {
     *     echo "High severity ReDoS vulnerability found in: " . $report->vulnerablePart;
     * }
     * ```
     */
    public function analyze(string $regex): ReDoSAnalysis
    {
        try {
            $ast = Regex::create()->parse($regex);
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
                },
                $result['vulnerablePattern'],
                $result['recommendations'],
            );
        } catch (\Throwable $e) {
            // Fallback for parsing errors, treat as unknown/safe or rethrow
            return new ReDoSAnalysis(ReDoSSeverity::SAFE, 0, null, ['Error parsing regex: '.$e->getMessage()]);
        }
    }
}
