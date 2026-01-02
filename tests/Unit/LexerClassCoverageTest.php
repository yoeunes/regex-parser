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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\TokenType;

final class LexerClassCoverageTest extends TestCase
{
    public function test_lexer_tokenizes_literals_and_eof(): void
    {
        $lexer = new Lexer();

        $tokenStream = $lexer->tokenize('test');
        $tokens = $tokenStream->getTokens();

        $this->assertSame('test', $tokenStream->getPattern());
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame('t', $tokens[0]->value);
        $this->assertSame(TokenType::T_EOF, $tokens[\count($tokens) - 1]->type);
    }
}
