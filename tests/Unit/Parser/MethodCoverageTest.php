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

namespace RegexParser\Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

/**
 * Tests specifically targeting uncovered methods to achieve 100% method coverage.
 */
final class MethodCoverageTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    /**
     * Test Parser.parseSubroutineName() via (?P>name) syntax
     * This triggers the parseSubroutineName() method
     */
    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_p_syntax(): void
    {
        // (?P>name) syntax - triggers parseSubroutineName
        $this->regexService->parse('/(?<foo>x)(?P>foo)/');
    }

    /**
     * Test Parser.parseSubroutineName() via (?&name) syntax
     * This also triggers the parseSubroutineName() method
     */
    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_ampersand_syntax(): void
    {
        // (?&name) syntax - triggers parseSubroutineName
        $this->regexService->parse('/(?<bar>y)(?&bar)/');
    }

    /**
     * Exercise repeated parse calls to cover parser/lexer setup paths.
     */
    #[DoesNotPerformAssertions]
    public function test_parser_get_lexer_multiple_calls(): void
    {
        // First call
        $this->regexService->parse('/test/');

        // Second call
        $this->regexService->parse('/another/');

        // Third call
        $this->regexService->parse('/pattern/');
    }

    /**
     * Test Parser.isAtEnd() via patterns that check end of input
     * The isAtEnd method is called in checkLiteral and other places
     */
    #[DoesNotPerformAssertions]
    public function test_parser_is_at_end(): void
    {
        // Simple patterns that cause isAtEnd checks
        $this->regexService->parse('/a/');
        $this->regexService->parse('/ab/');
        $this->regexService->parse('//'); // Empty pattern
    }

    /**
     * Test Lexer.consumeQuoteMode() with \Q...\E syntax
     * This triggers the private consumeQuoteMode() method
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_quote_mode(): void
    {
        // \Q...\E quote mode
        $this->regexService->parse('/\Qtest\E/');
        $this->regexService->parse('/\Qhello world\E/');
        $this->regexService->parse('/\Q.*+?{}[]()\E/'); // Special chars in quote mode
        $this->regexService->parse('/\Qunclosed/'); // Quote mode without \E
    }

    /**
     * Test Lexer.consumeCommentMode() with (?#...) syntax
     * This triggers the private consumeCommentMode() method
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_comment_mode_detailed(): void
    {
        // (?#...) comment mode
        $this->regexService->parse('/(?#simple comment)/');
        $this->regexService->parse('/(?#comment with spaces and punctuation!)/');
        $this->regexService->parse('/a(?#comment)b/');
        $this->regexService->parse('/(?#first)x(?#second)/');
        $this->regexService->parse('/(?#)/'); // Empty comment
    }

    /**
     * Test Lexer.extractTokenValue() with various token types
     * This triggers the private extractTokenValue() method for different token types
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_extract_token_value_comprehensive(): void
    {
        // T_LITERAL_ESCAPED with special escapes
        $this->regexService->parse('/\t/');  // Tab
        $this->regexService->parse('/\n/');  // Newline
        $this->regexService->parse('/\r/');  // Carriage return
        $this->regexService->parse('/\f/');  // Form feed
        $this->regexService->parse('/\v/');  // Vertical tab
        $this->regexService->parse('/\e/');  // Escape
        $this->regexService->parse('/\./');  // Escaped dot

        // T_PCRE_VERB
        $this->regexService->parse('/(*FAIL)/');
        $this->regexService->parse('/(*ACCEPT)/');
        $this->regexService->parse('/(*COMMIT)/');

        // T_ASSERTION
        $this->regexService->parse('/\b/');  // Word boundary
        $this->regexService->parse('/\B/');  // Not word boundary
        $this->regexService->parse('/\A/');  // Start of string
        $this->regexService->parse('/\Z/');  // End of string
        $this->regexService->parse('/\z/');  // Absolute end

        // T_CHAR_TYPE
        $this->regexService->parse('/\d/');  // Digit
        $this->regexService->parse('/\D/');  // Not digit
        $this->regexService->parse('/\w/');  // Word char
        $this->regexService->parse('/\W/');  // Not word char
        $this->regexService->parse('/\s/');  // Whitespace
        $this->regexService->parse('/\S/');  // Not whitespace

        // T_KEEP
        $this->regexService->parse('/\K/');

        // T_BACKREF
        $this->regexService->parse('/(a)\1/');
        $this->regexService->parse('/(a)(b)\2/');

        // T_OCTAL_LEGACY
        $this->regexService->parse('/\01/');
        $this->regexService->parse('/\77/');

        // T_POSIX_CLASS
        $this->regexService->parse('/[[:alnum:]]/');
        $this->regexService->parse('/[[:alpha:]]/');
        $this->regexService->parse('/[[:digit:]]/');
    }

    /**
     * Test Lexer.normalizeUnicodeProp() with Unicode property patterns
     * This private method handles \p{} and \P{} normalization
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_normalize_unicode_prop(): void
    {
        // \p{L} - regular property
        $this->regexService->parse('/\p{L}/');

        // \P{L} - negated property (adds ^)
        $this->regexService->parse('/\P{L}/');

        // \p{^L} - already negated
        $this->regexService->parse('/\p{^L}/');

        // \P{^L} - double negation (removes ^)
        $this->regexService->parse('/\P{^L}/');

        // Various Unicode properties
        $this->regexService->parse('/\p{Ll}/');  // Lowercase letter
        $this->regexService->parse('/\P{Lu}/');  // Not uppercase letter
        $this->regexService->parse('/\p{N}/');   // Number
        $this->regexService->parse('/\P{P}/');   // Not punctuation
        $this->regexService->parse('/\p{Sc}/');  // Currency symbol
        $this->regexService->parse('/\P{Sc}/');  // Not currency symbol
    }
}
