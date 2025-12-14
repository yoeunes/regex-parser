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

/**
 * Represents a single token from the lexer.
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $position,
    ) {}
}
