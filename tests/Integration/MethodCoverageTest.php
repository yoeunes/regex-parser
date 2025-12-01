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

/**
 * Tests specifically targeting uncovered methods to achieve 100% method coverage.
 */
class MethodCoverageTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    /**
     * Test Parser.parseSubroutineName() via (?P>name) syntax
     * This triggers the parseSubroutineName() method
     */
    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_p_syntax(): void
    {
        // (?P>name) syntax - triggers parseSubroutineName
        $this->regex->parse('/(?<foo>x)(?P>foo)/');
    }

    /**
     * Test Parser.parseSubroutineName() via (?&name) syntax
     * This also triggers the parseSubroutineName() method
     */
    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_ampersand_syntax(): void
    {
        // (?&name) syntax - triggers parseSubroutineName
        $this->regex->parse('/(?<bar>y)(?&bar)/');
    }

    /**
     * Test Parser.getLexer() by calling parse which internally uses getLexer
     * Multiple calls ensure the lexer reuse path is tested
     */
    #[DoesNotPerformAssertions]
    public function test_parser_get_lexer_multiple_calls(): void
    {
        // First call creates lexer
        $this->regex->parse('/test/');

        // Second call should reuse lexer via getLexer
        $this->regex->parse('/another/');

        // Third call
        $this->regex->parse('/pattern/');
    }

    /**
     * Test Parser.isAtEnd() via patterns that check end of input
     * The isAtEnd method is called in checkLiteral and other places
     */
    #[DoesNotPerformAssertions]
    public function test_parser_is_at_end(): void
    {
        // Simple patterns that cause isAtEnd checks
        $this->regex->parse('/a/');
        $this->regex->parse('/ab/');
        $this->regex->parse('//'); // Empty pattern
    }

    /**
     * Test Lexer.consumeQuoteMode() with \Q...\E syntax
     * This triggers the private consumeQuoteMode() method
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_quote_mode(): void
    {
        // \Q...\E quote mode
        $this->regex->parse('/\Qtest\E/');
        $this->regex->parse('/\Qhello world\E/');
        $this->regex->parse('/\Q.*+?{}[]()\E/'); // Special chars in quote mode
        $this->regex->parse('/\Qunclosed/'); // Quote mode without \E
    }

    /**
     * Test Lexer.consumeCommentMode() with (?#...) syntax
     * This triggers the private consumeCommentMode() method
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_comment_mode_detailed(): void
    {
        // (?#...) comment mode
        $this->regex->parse('/(?#simple comment)/');
        $this->regex->parse('/(?#comment with spaces and punctuation!)/');
        $this->regex->parse('/a(?#comment)b/');
        $this->regex->parse('/(?#first)x(?#second)/');
        $this->regex->parse('/(?#)/'); // Empty comment
    }

    /**
     * Test Lexer.extractTokenValue() with various token types
     * This triggers the private extractTokenValue() method for different token types
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_extract_token_value_comprehensive(): void
    {
        // T_LITERAL_ESCAPED with special escapes
        $this->regex->parse('/\t/');  // Tab
        $this->regex->parse('/\n/');  // Newline
        $this->regex->parse('/\r/');  // Carriage return
        $this->regex->parse('/\f/');  // Form feed
        $this->regex->parse('/\v/');  // Vertical tab
        $this->regex->parse('/\e/');  // Escape
        $this->regex->parse('/\./');  // Escaped dot

        // T_PCRE_VERB
        $this->regex->parse('/(*FAIL)/');
        $this->regex->parse('/(*ACCEPT)/');
        $this->regex->parse('/(*COMMIT)/');

        // T_ASSERTION
        $this->regex->parse('/\b/');  // Word boundary
        $this->regex->parse('/\B/');  // Not word boundary
        $this->regex->parse('/\A/');  // Start of string
        $this->regex->parse('/\Z/');  // End of string
        $this->regex->parse('/\z/');  // Absolute end

        // T_CHAR_TYPE
        $this->regex->parse('/\d/');  // Digit
        $this->regex->parse('/\D/');  // Not digit
        $this->regex->parse('/\w/');  // Word char
        $this->regex->parse('/\W/');  // Not word char
        $this->regex->parse('/\s/');  // Whitespace
        $this->regex->parse('/\S/');  // Not whitespace

        // T_KEEP
        $this->regex->parse('/\K/');

        // T_BACKREF
        $this->regex->parse('/(a)\1/');
        $this->regex->parse('/(a)(b)\2/');

        // T_OCTAL_LEGACY
        $this->regex->parse('/\01/');
        $this->regex->parse('/\77/');

        // T_POSIX_CLASS
        $this->regex->parse('/[[:alnum:]]/');
        $this->regex->parse('/[[:alpha:]]/');
        $this->regex->parse('/[[:digit:]]/');
    }

    /**
     * Test Lexer directly to ensure __construct, reset, and tokenize are covered
     */
    public function test_lexer_direct_instantiation(): void
    {
        // Direct Lexer instantiation
        $tokens = new Lexer()->tokenize('test')->getTokens();
        $this->assertIsArray($tokens);
    }
}
