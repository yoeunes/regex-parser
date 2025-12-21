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
 * @api
 */
final readonly class ReDoSAnalysis
{
    public ?string $vulnerableSubpattern;

    /**
     * @param list<string>       $recommendations
     * @param list<ReDoSFinding> $findings
     */
    public function __construct(
        public ReDoSSeverity $severity,
        public int $score,
        public ?string $vulnerablePart = null,
        public array $recommendations = [],
        public ?string $error = null,
        ?string $vulnerableSubpattern = null,
        public ?string $trigger = null,
        public ?ReDoSConfidence $confidence = null,
        public ?string $falsePositiveRisk = null,
        public array $findings = [],
        public ?string $suggestedRewrite = null,
    ) {
        $this->vulnerableSubpattern = $vulnerableSubpattern ?? $vulnerablePart;
    }

    public function getVulnerableSubpattern(): ?string
    {
        return $this->vulnerableSubpattern ?? $this->vulnerablePart;
    }

    public function isSafe(): bool
    {
        return ReDoSSeverity::SAFE === $this->severity || ReDoSSeverity::LOW === $this->severity;
    }

    public function exceedsThreshold(ReDoSSeverity $threshold): bool
    {
        return $this->severityScore($this->severity) >= $this->severityScore($threshold);
    }

    private function severityScore(ReDoSSeverity $severity): int
    {
        return match ($severity) {
            ReDoSSeverity::SAFE => 0,
            ReDoSSeverity::LOW => 1,
            ReDoSSeverity::UNKNOWN => 2,
            ReDoSSeverity::MEDIUM => 3,
            ReDoSSeverity::HIGH => 4,
            ReDoSSeverity::CRITICAL => 5,
        };
    }
}
