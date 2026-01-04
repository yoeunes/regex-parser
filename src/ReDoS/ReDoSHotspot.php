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
 * Identifies a vulnerable span within the pattern for heatmap rendering.
 */
final readonly class ReDoSHotspot implements \JsonSerializable
{
    public function __construct(
        public int $start,
        public int $end,
        public ReDoSSeverity $severity,
        public string $pattern,
        public ?string $trigger = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'severity' => $this->severity->value,
            'pattern' => $this->pattern,
            'trigger' => $this->trigger,
        ];
    }
}
