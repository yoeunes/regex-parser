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

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RegexParser\Lexer;
use RegexParser\Parser;
use RegexParser\TokenType;

/**
 * Tests to improve code coverage for the Lexer class.
 * Specifically targeting uncovered methods: consumeQuoteMode, extractTokenValue, and normalizeUnicodeProp.
 */
class LexerCoverageBoostTest extends TestCase
{
    /**
     * Test \Q...\E quote mode - ensures consumeQuoteMode() is called.
     */
    public function test_quote_mode_basic(): void
    {
        $lexer = new Lexer('\Qhello world\E');
        $tokens = $lexer->tokenizeToArray();

        // Should have: T_QUOTE_MODE_START, T_LITERAL, T_QUOTE_MODE_END, T_EOF
        $this->assertGreaterThan(0, \count($tokens));
        $hasLiteral = array_any($tokens, fn ($token) => TokenType::T_LITERAL === $token->type && str_contains((string) $token->value, 'hello'));
        $this->assertTrue($hasLiteral, 'Should tokenize quoted text');
    }

    public function test_quote_mode_with_special_chars(): void
    {
        $lexer = new Lexer('\Q.*+?[]{}()\E');
        $tokens = $lexer->tokenizeToArray();

        // All special chars should be treated as literals inside \Q...\E
        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_quote_mode_without_end(): void
    {
        // \Q without \E - should treat rest of pattern as literal
        $lexer = new Lexer('\Qhello world');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_quote_mode_empty(): void
    {
        // Empty quote mode
        $lexer = new Lexer('\Q\E');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_quote_mode_nested_backslashes(): void
    {
        // Test \Q with backslashes inside
        $lexer = new Lexer('\Q\\n\\t\E');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
    }

    /**
     * Test special escape sequences - ensures extractTokenValue() handles them correctly.
     */
    public function test_escape_tab(): void
    {
        $lexer = new Lexer('\t');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // \t + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\t", $tokens[0]->value);
    }

    public function test_escape_newline(): void
    {
        $lexer = new Lexer('\n');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // \n + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\n", $tokens[0]->value);
    }

    public function test_escape_carriage_return(): void
    {
        $lexer = new Lexer('\r');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // \r + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\r", $tokens[0]->value);
    }

    public function test_escape_form_feed(): void
    {
        $lexer = new Lexer('\f');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // \f + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\f", $tokens[0]->value);
    }

    public function test_escape_vertical_tab(): void
    {
        // Note: \v in PCRE is a vertical whitespace character type, not a literal escape
        $lexer = new Lexer('\v');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // \v + EOF
        $this->assertSame(TokenType::T_CHAR_TYPE, $tokens[0]->type);
        $this->assertSame('v', $tokens[0]->value);
    }

    public function test_escape_escape_char(): void
    {
        $lexer = new Lexer('\e');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // \e + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\e", $tokens[0]->value);
    }

    public function test_all_special_escapes_together(): void
    {
        // Note: \v is a char type, not a literal escape, so we test only actual escapes
        $lexer = new Lexer('\t\n\r\f\e');
        $tokens = $lexer->tokenizeToArray();

        // 5 escape sequences + EOF
        $this->assertCount(6, $tokens);
        $this->assertSame("\t", $tokens[0]->value);
        $this->assertSame("\n", $tokens[1]->value);
        $this->assertSame("\r", $tokens[2]->value);
        $this->assertSame("\f", $tokens[3]->value);
        $this->assertSame("\e", $tokens[4]->value);
    }

    /**
     * Test Unicode property variations - ensures normalizeUnicodeProp() is called.
     */
    public function test_unicode_prop_lowercase_p(): void
    {
        $lexer = new Lexer('\p{L}');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('L', $tokens[0]->value);
    }

    public function test_unicode_prop_uppercase_p(): void
    {
        // \P{L} should be negated to ^L
        $lexer = new Lexer('\P{L}');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('^L', $tokens[0]->value);
    }

    public function test_unicode_prop_lowercase_p_with_negation(): void
    {
        // \p{^L} should remain ^L
        $lexer = new Lexer('\p{^L}');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('^L', $tokens[0]->value);
    }

    public function test_unicode_prop_uppercase_p_with_negation_double_negation(): void
    {
        // \P{^L} should be double negation -> L
        $lexer = new Lexer('\P{^L}');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('L', $tokens[0]->value);
    }

    public function test_unicode_prop_short_form_lowercase(): void
    {
        // \pL (short form)
        $lexer = new Lexer('\pL');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('L', $tokens[0]->value);
    }

    public function test_unicode_prop_short_form_uppercase(): void
    {
        // \PL (short form) should be negated to ^L
        $lexer = new Lexer('\PL');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('^L', $tokens[0]->value);
    }

    public function test_unicode_prop_various_categories(): void
    {
        $patterns = [
            '\p{Lu}',  // Uppercase letter
            '\P{Lu}',  // Not uppercase letter
            '\p{Nd}',  // Decimal digit
            '\P{Nd}',  // Not decimal digit
            '\p{Sc}',  // Currency symbol
            '\P{Sc}',  // Not currency symbol
        ];

        foreach ($patterns as $pattern) {
            $lexer = new Lexer($pattern);
            $tokens = $lexer->tokenizeToArray();
            $this->assertGreaterThan(0, \count($tokens));
            $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        }
    }

    /**
     * Test through Parser to ensure integration works correctly.
     */
    #[DoesNotPerformAssertions]
    public function test_parser_with_quote_mode(): void
    {
        $parser = new Parser();
        $parser->parse('/\Qtest.*\E/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_with_special_escapes(): void
    {
        $parser = new Parser();
        $parser->parse('/\t\n\r/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_with_unicode_props(): void
    {
        $parser = new Parser();

        $patterns = [
            '/\p{L}/',
            '/\P{L}/',
            '/\p{^L}/',
            '/\P{^L}/',
        ];

        foreach ($patterns as $pattern) {
            $parser->parse($pattern);
        }
    }

    /**
     * Additional edge cases for better coverage.
     */
    public function test_quote_mode_multiple(): void
    {
        $lexer = new Lexer('\Qabc\Edef\Qghi\E');
        $tokens = $lexer->tokenizeToArray();

        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_escaped_literal_other_chars(): void
    {
        // Test other escaped literals that aren't special escapes
        $lexer = new Lexer('\.\*\+\?');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(5, $tokens); // 4 escaped literals + EOF
        $this->assertSame('.', $tokens[0]->value);
        $this->assertSame('*', $tokens[1]->value);
        $this->assertSame('+', $tokens[2]->value);
        $this->assertSame('?', $tokens[3]->value);
    }

    public function test_pcre_verb_extraction(): void
    {
        // Test PCRE verb value extraction
        $lexer = new Lexer('(*FAIL)');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // (*FAIL) + EOF
        $this->assertSame(TokenType::T_PCRE_VERB, $tokens[0]->type);
        $this->assertSame('FAIL', $tokens[0]->value);
    }

    public function test_pcre_verb_with_argument(): void
    {
        $lexer = new Lexer('(*MARK:foo)');
        $tokens = $lexer->tokenizeToArray();

        $this->assertCount(2, $tokens); // (*MARK:foo) + EOF
        $this->assertSame(TokenType::T_PCRE_VERB, $tokens[0]->type);
        $this->assertSame('MARK:foo', $tokens[0]->value);
    }
}
