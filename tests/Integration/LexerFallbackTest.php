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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;

class LexerFallbackTest extends TestCase
{
    /**
     * Covers the case where lexQuoteMode reaches the end of the string without finding \E.
     */
    public function test_lex_quote_mode_unterminated(): void
    {
        $lexer = new Lexer('\Qstart'); // No \E
        $tokens = $lexer->tokenize();

        // Must contain T_LITERAL "start" and T_EOF
        $this->assertCount(2, $tokens);
        $this->assertSame('start', $tokens[0]->value);
    }

    /**
     * Covers the case where lexCommentMode reaches the end of the string without finding ).
     * (Note: this throws an exception later in tokenize(), but we want to test the internal call).
     */
    public function test_lex_comment_mode_unterminated_internal(): void
    {
        $lexer = new Lexer('(?#start'); // No )

        try {
            $lexer->tokenize();
        } catch (\Exception $e) {
            // We expect an exception, but we mainly want the code to be executed
            $this->assertStringContainsString('Unclosed comment', $e->getMessage());
        }
    }
}
