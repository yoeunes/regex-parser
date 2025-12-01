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

readonly class ReDoSAnalysis
{
    /**
     * @param array<string> $recommendations
     */
    public function __construct(
        public ReDoSSeverity $severity,
        public int $score,
        public ?string $vulnerablePart = null,
        public array $recommendations = [],
    ) {}

    public function isSafe(): bool
    {
        return ReDoSSeverity::SAFE === $this->severity || ReDoSSeverity::LOW === $this->severity;
    }
}
