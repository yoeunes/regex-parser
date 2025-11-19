<?php

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\LexerException;
use RegexParser\Lexer;

class LexerUtf8Test extends TestCase
{
    public function test_lexer_throws_on_invalid_utf8(): void
    {
        // Invalid UTF-8 sequence (0xC3 without a following byte)
        $invalidUtf8 = "abc\xC3";

        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Input string is not valid UTF-8');

        new Lexer($invalidUtf8);
    }
}
