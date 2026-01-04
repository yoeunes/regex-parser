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

use RegexParser\Node\NodeInterface;

/**
 * Encapsulates the results of a Regular Expression Denial of Service (ReDoS) analysis.
 *
 * @api
 */
final readonly class ReDoSAnalysis implements \JsonSerializable
{
    public ?string $vulnerableSubpattern;

    /**
     * @param array<string>       $recommendations
     * @param array<ReDoSFinding> $findings
     * @param array<ReDoSHotspot> $hotspots
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
        private ?NodeInterface $culpritNode = null,
        public array $hotspots = [],
        public ReDoSMode $mode = ReDoSMode::THEORETICAL,
        public ?ReDoSConfirmation $confirmation = null,
    ) {
        $this->vulnerableSubpattern = $vulnerableSubpattern ?? $vulnerablePart;
    }

    public function getVulnerableSubpattern(): ?string
    {
        return $this->vulnerableSubpattern ?? $this->vulnerablePart;
    }

    public function getCulpritNode(): ?NodeInterface
    {
        return $this->culpritNode;
    }

    public function isSafe(): bool
    {
        return ReDoSSeverity::SAFE === $this->severity || ReDoSSeverity::LOW === $this->severity;
    }

    public function isConfirmed(): bool
    {
        return ReDoSMode::CONFIRMED === $this->mode && (null !== $this->confirmation && $this->confirmation->confirmed);
    }

    public function confidenceLevel(): ReDoSConfidence
    {
        return $this->confidence ?? ReDoSConfidence::LOW;
    }

    public function getPrimaryHotspot(): ?ReDoSHotspot
    {
        $best = null;
        $bestRank = -1;

        foreach ($this->hotspots as $hotspot) {
            if (!$hotspot instanceof ReDoSHotspot) {
                continue;
            }

            $rank = $this->severityScore($hotspot->severity);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $hotspot;
            }
        }

        return $best;
    }

    public function exceedsThreshold(ReDoSSeverity $threshold): bool
    {
        return $this->severityScore($this->severity) >= $this->severityScore($threshold);
    }

    /**
     * @return array{
     *     severity: string,
     *     score: int,
     *     mode: string,
     *     confirmed: bool,
     *     confidence: string,
     *     vulnerable_part: string|null,
     *     vulnerable_subpattern: string|null,
     *     trigger: string|null,
     *     false_positive_risk: string|null,
     *     suggested_rewrite: string|null,
     *     recommendations: array<int|string, string>,
     *     error: string|null,
     *     findings: array<int|string, ReDoSFinding>,
     *     hotspots: array<int|string, ReDoSHotspot>,
     *     confirmation: ReDoSConfirmation|null,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'severity' => $this->severity->value,
            'score' => $this->score,
            'mode' => $this->mode->value,
            'confirmed' => $this->isConfirmed(),
            'confidence' => $this->confidenceLevel()->value,
            'vulnerable_part' => $this->vulnerablePart,
            'vulnerable_subpattern' => $this->vulnerableSubpattern,
            'trigger' => $this->trigger,
            'false_positive_risk' => $this->falsePositiveRisk,
            'suggested_rewrite' => $this->suggestedRewrite,
            'recommendations' => $this->recommendations,
            'error' => $this->error,
            'findings' => $this->findings,
            'hotspots' => $this->hotspots,
            'confirmation' => $this->confirmation,
        ];
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
