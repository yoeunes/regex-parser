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

namespace RegexParser\Tests\Lexer;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\TokenType;

class QuoteModeTest extends TestCase
{
    public function test_quote_mode_with_end_delimiter(): void
    {
        // \Q ... \E
        $lexer = new Lexer('\Q*+?\E');
        $tokens = $lexer->tokenize();

        // Should result in a single LITERAL token "*+?"
        $this->assertCount(2, $tokens); // Literal + EOF
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame('*+?', $tokens[0]->value);
    }

    public function test_quote_mode_until_end_of_string(): void
    {
        // \Q ... (no \E)
        $lexer = new Lexer('\Q*+?');
        $tokens = $lexer->tokenize();

        // Should treat everything after \Q as literal until EOF
        $this->assertCount(2, $tokens);
        $this->assertSame(TokenType::T_LITERAL, $tokens[0]->type);
        $this->assertSame('*+?', $tokens[0]->value);
    }

    public function test_empty_quote_mode(): void
    {
        // \Q\E
        $lexer = new Lexer('a\Q\Eb');
        $tokens = $lexer->tokenize();

        // Should produce literal 'a', then literal 'b'.
        // \Q\E produces nothing or empty literal (logic check).
        // Current logic: consumeQuoteMode returns null if empty content inside.

        $this->assertSame('a', $tokens[0]->value);
        $this->assertSame('b', $tokens[1]->value);
    }
}
