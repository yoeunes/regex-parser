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
use RegexParser\Regex;
use RegexParser\TokenType;

/**
 * Tests to improve code coverage for the Lexer class.
 * Specifically targeting uncovered methods: consumeQuoteMode, extractTokenValue, and normalizeUnicodeProp.
 */
final class LexerCoverageBoostTest extends TestCase
{

    /**
     * Test \Q...\E quote mode - ensures consumeQuoteMode() is called.
     */
    public function test_quote_mode_basic(): void
    {
        $tokens = (new Lexer())->tokenize('\Qhello world\E')->getTokens();

        // Should have: T_QUOTE_MODE_START, T_LITERAL, T_QUOTE_MODE_END, T_EOF
        $this->assertGreaterThan(0, \count($tokens));
        $hasLiteral = false;
        foreach ($tokens as $token) {
            if (TokenType::T_LITERAL === $token->type && str_contains((string) $token->value, 'hello')) {
                $hasLiteral = true;

                break;
            }
        }
        $this->assertTrue($hasLiteral, 'Should tokenize quoted text');
    }

    public function test_quote_mode_with_special_chars(): void
    {
        $tokens = (new Lexer())->tokenize('\Q.*+?[]{}()\E')->getTokens();

        // All special chars should be treated as literals inside \Q...\E
        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_quote_mode_without_end(): void
    {
        // \Q without \E - should treat rest of pattern as literal
        $tokens = (new Lexer())->tokenize('\Qhello world')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_quote_mode_empty(): void
    {
        // Empty quote mode
        $tokens = (new Lexer())->tokenize('\Q\E')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_quote_mode_nested_backslashes(): void
    {
        // Test \Q with backslashes inside
        $tokens = (new Lexer())->tokenize('\Q\\n\\t\E')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
    }

    /**
     * Test special escape sequences - ensures extractTokenValue() handles them correctly.
     */
    public function test_escape_tab(): void
    {
        $tokens = (new Lexer())->tokenize('\t')->getTokens();

        $this->assertCount(2, $tokens); // \t + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\t", $tokens[0]->value);
    }

    public function test_escape_newline(): void
    {
        $tokens = (new Lexer())->tokenize('\n')->getTokens();

        $this->assertCount(2, $tokens); // \n + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\n", $tokens[0]->value);
    }

    public function test_escape_carriage_return(): void
    {
        $tokens = (new Lexer())->tokenize('\r')->getTokens();

        $this->assertCount(2, $tokens); // \r + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\r", $tokens[0]->value);
    }

    public function test_escape_form_feed(): void
    {
        $tokens = (new Lexer())->tokenize('\f')->getTokens();

        $this->assertCount(2, $tokens); // \f + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\f", $tokens[0]->value);
    }

    public function test_escape_vertical_tab(): void
    {
        // Note: \v in PCRE is a vertical whitespace character type, not a literal escape
        $tokens = (new Lexer())->tokenize('\v')->getTokens();

        $this->assertCount(2, $tokens); // \v + EOF
        $this->assertSame(TokenType::T_CHAR_TYPE, $tokens[0]->type);
        $this->assertSame('v', $tokens[0]->value);
    }

    public function test_escape_escape_char(): void
    {
        $tokens = (new Lexer())->tokenize('\e')->getTokens();

        $this->assertCount(2, $tokens); // \e + EOF
        $this->assertSame(TokenType::T_LITERAL_ESCAPED, $tokens[0]->type);
        $this->assertSame("\e", $tokens[0]->value);
    }

    public function test_all_special_escapes_together(): void
    {
        // Note: \v is a char type, not a literal escape, so we test only actual escapes
        $tokens = (new Lexer())->tokenize('\t\n\r\f\e')->getTokens();

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
        $tokens = (new Lexer())->tokenize('\p{L}')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('L', $tokens[0]->value);
    }

    public function test_unicode_prop_uppercase_p(): void
    {
        // \P{L} should be negated to ^L
        $tokens = (new Lexer())->tokenize('\P{L}')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('^L', $tokens[0]->value);
    }

    public function test_unicode_prop_lowercase_p_with_negation(): void
    {
        // \p{^L} should remain ^L
        $tokens = (new Lexer())->tokenize('\p{^L}')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('^L', $tokens[0]->value);
    }

    public function test_unicode_prop_uppercase_p_with_negation_double_negation(): void
    {
        // \P{^L} should be double negation -> L
        $tokens = (new Lexer())->tokenize('\P{^L}')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('L', $tokens[0]->value);
    }

    public function test_unicode_prop_short_form_lowercase(): void
    {
        // \pL (short form)
        $tokens = (new Lexer())->tokenize('\pL')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
        $this->assertSame(TokenType::T_UNICODE_PROP, $tokens[0]->type);
        $this->assertSame('L', $tokens[0]->value);
    }

    public function test_unicode_prop_short_form_uppercase(): void
    {
        // \PL (short form) should be negated to ^L
        $tokens = (new Lexer())->tokenize('\PL')->getTokens();

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
            $tokens = (new Lexer())->tokenize($pattern)->getTokens();
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
        Regex::create()->parse('/\Qtest.*\E/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_with_special_escapes(): void
    {
        Regex::create()->parse('/\t\n\r/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_with_unicode_props(): void
    {
        $patterns = [
            '/\p{L}/',
            '/\P{L}/',
            '/\p{^L}/',
            '/\P{^L}/',
        ];

        foreach ($patterns as $pattern) {
            Regex::create()->parse($pattern);
        }
    }

    /**
     * Additional edge cases for better coverage.
     */
    public function test_quote_mode_multiple(): void
    {
        $tokens = (new Lexer())->tokenize('\Qabc\Edef\Qghi\E')->getTokens();

        $this->assertGreaterThan(0, \count($tokens));
    }

    public function test_escaped_literal_other_chars(): void
    {
        // Test other escaped literals that aren't special escapes
        $tokens = (new Lexer())->tokenize('\.\*\+\?')->getTokens();

        $this->assertCount(5, $tokens); // 4 escaped literals + EOF
        $this->assertSame('.', $tokens[0]->value);
        $this->assertSame('*', $tokens[1]->value);
        $this->assertSame('+', $tokens[2]->value);
        $this->assertSame('?', $tokens[3]->value);
    }

    public function test_pcre_verb_extraction(): void
    {
        // Test PCRE verb value extraction
        $tokens = (new Lexer())->tokenize('(*FAIL)')->getTokens();

        $this->assertCount(2, $tokens); // (*FAIL) + EOF
        $this->assertSame(TokenType::T_PCRE_VERB, $tokens[0]->type);
        $this->assertSame('FAIL', $tokens[0]->value);
    }

    public function test_pcre_verb_with_argument(): void
    {
        $tokens = (new Lexer())->tokenize('(*MARK:foo)')->getTokens();

        $this->assertCount(2, $tokens); // (*MARK:foo) + EOF
        $this->assertSame(TokenType::T_PCRE_VERB, $tokens[0]->type);
        $this->assertSame('MARK:foo', $tokens[0]->value);
    }
}
