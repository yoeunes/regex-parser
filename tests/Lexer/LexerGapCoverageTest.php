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
use RegexParser\TokenType;

class LexerGapCoverageTest extends TestCase
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

        self::assertNull($result);
        self::assertSame(\strlen($invalid), $accessor->getPosition());
        self::assertFalse($accessor->getInQuoteMode());
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

        self::assertNull($result);
        self::assertSame(\strlen($pattern), $accessor->getPosition());
        self::assertTrue($accessor->getInQuoteMode());
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

        self::assertNull($result);
        self::assertSame(\strlen($invalid), $accessor->getPosition());
        self::assertFalse($this->getPrivateBool($lexer, 'inCommentMode'));
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

        self::assertNull($result);
        self::assertSame(\strlen($pattern), $accessor->getPosition());
        self::assertTrue($this->getPrivateBool($lexer, 'inCommentMode'));
    }

    public function test_extract_token_value_handles_bell_escape(): void
    {
        $lexer = new Lexer();
        $accessor = new LexerAccessor($lexer);

        $value = $accessor->callPrivateMethod('extractTokenValue', [TokenType::T_LITERAL_ESCAPED, '\\a', []]);

        self::assertSame("\x07", $value);
    }

    private function getPrivateBool(object $object, string $property): bool
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);

        return (bool) $ref->getValue($object);
    }
}
