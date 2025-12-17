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

namespace RegexParser\Bridge\Symfony\Command;

/**
 * Represents a regex pattern occurrence found in source code.
 *
 * @internal
 */
final readonly class RegexPatternOccurrence
{
    public function __construct(
        public string $pattern,
        public string $file,
        public int $line,
        public string $source,
    ) {}
}
