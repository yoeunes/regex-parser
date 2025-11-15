<?php

namespace RegexParser\Tests\Lexer;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\LexerException;
use RegexParser\Lexer\Lexer;
use RegexParser\Lexer\TokenType;

class LexerTest extends TestCase
{
    public function testTokenizeSimpleLiteral(): void
    {
        $lexer = new Lexer('/foo/');
        $tokens = $lexer->tokenize();

        // / f o o / EOF = 6 tokens
        $this->assertCount(6, $tokens);
        $this->assertSame(TokenType::T_DELIMITER, $tokens[0]->type);
        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type);
        $this->assertSame('f', $tokens[1]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[2]->type);
        $this->assertSame('o', $tokens[2]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[3]->type);
        $this->assertSame('o', $tokens[3]->value);
        $this->assertSame(TokenType::T_DELIMITER, $tokens[4]->type);
        $this->assertSame(TokenType::T_EOF, $tokens[5]->type);
    }

    public function testTokenizeGroupAndQuantifier(): void
    {
        $lexer = new Lexer('/(bar)?/');
        $tokens = $lexer->tokenize();

        $expected = [
            TokenType::T_DELIMITER,
            TokenType::T_GROUP_OPEN,
            TokenType::T_LITERAL, // b
            TokenType::T_LITERAL, // a
            TokenType::T_LITERAL, // r
            TokenType::T_GROUP_CLOSE,
            TokenType::T_QUANTIFIER, // ?
            TokenType::T_DELIMITER,
            TokenType::T_EOF,
        ];
        $this->assertCount(\count($expected), $tokens);
        $this->assertSame('?', $tokens[6]->value);

        foreach ($expected as $i => $type) {
            $this->assertSame($type, $tokens[$i]->type);
        }
    }

    public function testTokenizeAlternation(): void
    {
        $lexer = new Lexer('/foo|bar/');
        $tokens = $lexer->tokenize();
        // / f o o | b a r / EOF = 10 tokens
        $this->assertCount(10, $tokens);
        $this->assertSame(TokenType::T_ALTERNATION, $tokens[4]->type);
    }

    public function testTokenizeCustomQuantifier(): void
    {
        $lexer = new Lexer('/a{2,4}/');
        $tokens = $lexer->tokenize();

        // / a {2,4} / EOF = 5 tokens
        $this->assertCount(5, $tokens);
        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type);
        $this->assertSame(TokenType::T_QUANTIFIER, $tokens[2]->type);
        $this->assertSame('{2,4}', $tokens[2]->value);
    }

    public function testTokenizeEscapedChars(): void
    {
        $lexer = new Lexer('/\(a\*\)/'); // Regex: /\(a\*\)/
        $tokens = $lexer->tokenize();

        // / \ ( a \ * \ ) / EOF = 10 tokens
        $this->assertCount(10, $tokens);
        $this->assertSame(TokenType::T_DELIMITER, $tokens[0]->type);
        $this->assertSame(TokenType::T_BACKSLASH, $tokens[1]->type);
        $this->assertSame(TokenType::T_LITERAL, $tokens[2]->type); // (
        $this->assertSame('(', $tokens[2]->value);
        $this->assertSame(TokenType::T_LITERAL, $tokens[3]->type); // a
        $this->assertSame(TokenType::T_BACKSLASH, $tokens[4]->type);
        $this->assertSame(TokenType::T_LITERAL, $tokens[5]->type); // *
        $this->assertSame('*', $tokens[5]->value);
        $this->assertSame(TokenType::T_BACKSLASH, $tokens[6]->type); // \
        $this->assertSame(TokenType::T_LITERAL, $tokens[7]->type); // )
        $this->assertSame(')', $tokens[7]->value);
        $this->assertSame(TokenType::T_DELIMITER, $tokens[8]->type);
        $this->assertSame(TokenType::T_EOF, $tokens[9]->type);
    }

    public function testThrowsOnTrailingBackslash(): void
    {
        $this->expectException(LexerException::class);
        $this->expectExceptionMessage('Trailing backslash');
        $lexer = new Lexer('/foo\\');
        $lexer->tokenize();
    }
}
