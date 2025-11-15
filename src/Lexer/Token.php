<?php

namespace RegexParser\Lexer;

class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $position,
    ) {
    }
}
