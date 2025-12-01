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

/**
 * Encapsulates the results of a Regular Expression Denial of Service (ReDoS) analysis.
 *
 * Purpose: This immutable Data Transfer Object (DTO) provides a structured report from the
 * `ReDoSAnalyzer`. Instead of a simple boolean, it offers a detailed breakdown including
 * a severity level, a numerical complexity score, the specific part of the regex that is
 * vulnerable, and actionable recommendations for fixing it. This gives developers clear
 * insights into potential security risks.
 */
readonly class ReDoSAnalysis
{
    /**
     * Creates a new, immutable ReDoS analysis report.
     *
     * Purpose: This constructor initializes the report object that summarizes the findings
     * of a ReDoS scan. It's used by the `ReDoSAnalyzer` to package the results in a
     * structured and easily consumable format. As a contributor, you'll see this object
     * created at the end of the analysis process.
     *
     * @param ReDoSSeverity $severity        The overall severity of the detected vulnerability (e.g., HIGH, MEDIUM, LOW, SAFE).
     * @param int           $score           A numerical complexity score. Higher values indicate a more complex and
     *                                       potentially dangerous pattern.
     * @param string|null   $vulnerablePart  If a vulnerability is found, this holds the specific substring of the
     *                                       regex pattern that is causing the issue (e.g., `(a+)+`).
     * @param array<string> $recommendations A list of human-readable suggestions for how to mitigate the detected
     *                                       vulnerability (e.g., "Use atomic groups" or "Make quantifiers possessive").
     */
    public function __construct(
        public ReDoSSeverity $severity,
        public int $score,
        public ?string $vulnerablePart = null,
        public array $recommendations = [],
    ) {}

    /**
     * Determines if the regex is considered safe from high-impact ReDoS vulnerabilities.
     *
     * Purpose: This is a convenience method for developers to quickly check if a pattern
     * passes a basic safety threshold. It provides a simple boolean answer based on the
     * analysis's severity level. "Safe" in this context typically means no high or critical
     * vulnerabilities were detected, though low-risk issues might still exist.
     *
     * @return bool `true` if the severity is `SAFE` or `LOW`, `false` otherwise
     *
     * @example
     * ```php
     * $analysis = $redosAnalyzer->analyze('/(a+)+/');
     * if (!$analysis->isSafe()) {
     *     echo "Warning: Unsafe regex detected!";
     * }
     * ```
     */
    public function isSafe(): bool
    {
        return ReDoSSeverity::SAFE === $this->severity || ReDoSSeverity::LOW === $this->severity;
    }
}
