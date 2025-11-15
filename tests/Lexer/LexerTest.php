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
        $lexer = new Lexer('foo');
        $tokens = $lexer->tokenize();
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame('foo', $tokens[0]->value);
    }

    public function testTokenizeGroupAndQuantifier(): void
    {
        $lexer = new Lexer('(bar)?');
        $tokens = $lexer->tokenize();
        $this->assertCount(4, $tokens);
        $this->assertSame(TokenType::T_GROUP_OPEN, $tokens[0]->type);
        $this->assertSame(TokenType::T_LITERAL, $tokens[1]->type);
        $this->assertSame('bar', $tokens[1]->value);
        $this->assertSame(TokenType::T_GROUP_CLOSE, $tokens[2]->type);
        $this->assertSame(TokenType::T_QUANTIFIER, $tokens[3]->type);
        $this->assertSame('?', $tokens[3]->value);
    }

    public function testTokenizeAlternation(): void
    {
        $lexer = new Lexer('foo|bar');
        $tokens = $lexer->tokenize();
        $this->assertCount(3, $tokens);
        $this->assertSame('foo', $tokens[0]->value);
        $this->assertSame(TokenType::T_ALTERNATION, $tokens[1]->type);
        $this->assertSame('bar', $tokens[2]->value);
    }

    public function testTokenizeCustomQuantifier(): void
    {
        $lexer = new Lexer('{2,4}');
        $tokens = $lexer->tokenize();
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::T_QUANTIFIER, $tokens[0]->type);
        $this->assertSame('{2,4}', $tokens[0]->value);
    }

    public function testThrowsOnInvalidChar(): void
    {
        $this->expectException(LexerException::class);
        $lexer = new Lexer('@invalid');
        $lexer->tokenize();
    }
}
