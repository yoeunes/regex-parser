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

namespace RegexParser\Bundle\DataCollector;

/**
 * A DTO for holding regex information before analysis.
 */
class CollectedRegex
{
    public function __construct(
        public readonly string $pattern,
        public readonly string $source,
        public readonly ?string $subject,
        public readonly ?bool $matchResult,
    ) {}
}
