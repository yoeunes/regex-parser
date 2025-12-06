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
use RegexParser\Tests\TestUtils\LexerAccessor;
use RegexParser\Token as RegexToken;
use RegexParser\TokenType;

final class LexerGapCoverageTest extends TestCase
{
    public function test_consume_quote_mode_handles_pcre_error(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);
        $invalid = "\xC3"; // malformed UTF-8 byte to trigger preg_match failure with /u

        $accessor->setPattern($invalid);
        $accessor->setLength(\strlen($invalid));
        $accessor->setPosition(0);
        $accessor->setInQuoteMode(true);

        $result = $accessor->callPrivateMethod('consumeQuoteMode');

        $this->assertNull($result);
        $this->assertSame(\strlen($invalid), $accessor->getPosition());
        $this->assertFalse($accessor->getInQuoteMode());
    }

    public function test_consume_quote_mode_reaches_eof_without_closing(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);
        $pattern = '\Q';

        $accessor->setPattern($pattern);
        $accessor->setLength(\strlen($pattern));
        $accessor->setPosition(\strlen($pattern));
        $accessor->setInQuoteMode(true);

        $result = $accessor->callPrivateMethod('consumeQuoteMode');

        $this->assertNull($result);
        $this->assertSame(\strlen($pattern), $accessor->getPosition());
        $this->assertTrue($accessor->getInQuoteMode());
    }

    public function test_consume_quote_mode_returns_literal_and_exits_on_end_marker(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);
        $pattern = '\Qabc\E';

        $accessor->setPattern($pattern);
        $accessor->setLength(\strlen($pattern));
        $accessor->setPosition(2); // start after \Q
        $accessor->setInQuoteMode(true);

        $literalToken = $accessor->callPrivateMethod('consumeQuoteMode');
        $this->assertInstanceOf(RegexToken::class, $literalToken);
        $this->assertSame('abc', $literalToken->value);
        $this->assertSame(2, $literalToken->position);
        $this->assertTrue($accessor->getInQuoteMode());

        $endToken = $accessor->callPrivateMethod('consumeQuoteMode');
        $this->assertInstanceOf(RegexToken::class, $endToken);
        $this->assertSame(TokenType::T_QUOTE_MODE_END, $endToken->type);
        $this->assertFalse($accessor->getInQuoteMode());
    }

    public function test_consume_comment_mode_handles_pcre_error(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);
        $invalid = "\xC3";

        $accessor->setPattern($invalid);
        $accessor->setLength(\strlen($invalid));
        $accessor->setPosition(0);
        $accessor->setInCommentMode(true);

        $result = $accessor->callPrivateMethod('consumeCommentMode');

        $this->assertNull($result);
        $this->assertSame(\strlen($invalid), $accessor->getPosition());
        $this->assertFalse($this->getPrivateBool($lexer, 'inCommentMode'));
    }

    public function test_consume_comment_mode_unclosed_reaches_eof(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);
        $pattern = '(?#';

        $accessor->setPattern($pattern);
        $accessor->setLength(\strlen($pattern));
        $accessor->setPosition(\strlen($pattern));
        $accessor->setInCommentMode(true);

        $result = $accessor->callPrivateMethod('consumeCommentMode');

        $this->assertNull($result);
        $this->assertSame(\strlen($pattern), $accessor->getPosition());
        $this->assertTrue($this->getPrivateBool($lexer, 'inCommentMode'));
    }

    public function test_consume_comment_mode_returns_literal_and_closing_token(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);
        $pattern = 'text)';

        $accessor->setPattern($pattern);
        $accessor->setLength(\strlen($pattern));
        $accessor->setPosition(3); // after 'tex'
        $accessor->setInCommentMode(true);

        $literal = $accessor->callPrivateMethod('consumeCommentMode');
        $this->assertInstanceOf(RegexToken::class, $literal);
        $this->assertSame('t', $literal->value);
        $this->assertSame(3, $literal->position);
        $this->assertTrue($this->getPrivateBool($lexer, 'inCommentMode'));

        $closing = $accessor->callPrivateMethod('consumeCommentMode');
        $this->assertInstanceOf(RegexToken::class, $closing);
        $this->assertSame(TokenType::T_GROUP_CLOSE, $closing->type);
        $this->assertFalse($this->getPrivateBool($lexer, 'inCommentMode'));
    }

    public function test_extract_token_value_handles_bell_escape(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);

        $value = $accessor->callPrivateMethod('extractTokenValue', [TokenType::T_LITERAL_ESCAPED, '\\a', []]);

        $this->assertSame("\x07", $value);
    }

    public function test_extract_token_value_falls_back_to_default(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);

        $value = $accessor->callPrivateMethod('extractTokenValue', [TokenType::T_LITERAL, 'X', []]);

        $this->assertSame('X', $value);
    }

    private function getPrivateBool(object $object, string $property): bool
    {
        $ref = new \ReflectionProperty($object, $property);

        return (bool) $ref->getValue($object);
    }
}
