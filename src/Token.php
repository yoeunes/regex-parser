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
 * Data Transfer Object representing a single token.
 */
class Token
{
    /**
     * @param TokenType $type     The token type (e.g., T_LITERAL).
     * @param string    $value    The string value of the token (e.g., "a", "*", "\d").
     * @param int       $position the 0-based character offset in the original string
     */
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $position,
    ) {
    }
}
