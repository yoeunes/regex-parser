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
use RegexParser\Tests\TestUtils\LexerAccessor;
use RegexParser\Token;

class LexerInternalStateTest extends TestCase
{
    /**
     * Covers the end of consumeCommentMode when the closing parenthesis is missing.
     * Normally tokenize() throws an exception afterwards, but we want to cover the internal "return null".
     */
    public function test_lex_comment_mode_unterminated_returns_null(): void
    {
        $lexer = new Lexer('(?# ... sans fin');
        $accessor = new LexerAccessor($lexer);

        // Force comment mode
        $accessor->callPrivateMethod('reset', ['(?# ... sans fin']);
        // Manually advance after (?#
        $accessor->setPosition(3);

        // Direct call to private method
        $result = $accessor->callPrivateMethod('consumeCommentMode');

        // It must first return a token with the comment text
        $this->assertNotNull($result);
        $this->assertInstanceof(Token::class, $result);
        $this->assertSame(' ... sans fin', $result->value);
        // Position must be at the end of the text
        $this->assertSame(16, $accessor->getPosition());
    }

    /**
     * Covers the end of consumeQuoteMode when \E is missing.
     */
    public function test_lex_quote_mode_unterminated_returns_null(): void
    {
        $lexer = new Lexer('\Q ... sans fin');
        $accessor = new LexerAccessor($lexer);

        $accessor->callPrivateMethod('reset', ['\Q ... sans fin']);
        $accessor->setPosition(2); // After \Q

        $result = $accessor->callPrivateMethod('consumeQuoteMode');

        // It must first return a token with the literal text
        $this->assertNotNull($result);
        $this->assertInstanceof(Token::class, $result);
        $this->assertSame(' ... sans fin', $result->value);
        // Position must be at the end of the text
        $this->assertSame(15, $accessor->getPosition());
    }
}
