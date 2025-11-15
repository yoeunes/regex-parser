<?php

namespace RegexParser\Lexer;

/**
 * Data Transfer Object representing a single token.
 */
class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $position,
    ) {
    }
}
