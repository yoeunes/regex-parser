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

/**
 * Tests specifically targeting uncovered methods to achieve 100% method coverage.
 */
class MethodCoverageTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->compiler = new RegexCompiler([]);
    }

    /**
     * Test Parser.parseSubroutineName() via (?P>name) syntax
     * This triggers the parseSubroutineName() method
     */
    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_p_syntax(): void
    {
        // (?P>name) syntax - triggers parseSubroutineName
        $this->parser->parse('/(?<foo>x)(?P>foo)/');
    }

    /**
     * Test Parser.parseSubroutineName() via (?&name) syntax
     * This also triggers the parseSubroutineName() method
     */
    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_ampersand_syntax(): void
    {
        // (?&name) syntax - triggers parseSubroutineName
        $this->parser->parse('/(?<bar>y)(?&bar)/');
    }

    /**
     * Test Parser.getLexer() by calling parse which internally uses getLexer
     * Multiple calls ensure the lexer reuse path is tested
     */
    #[DoesNotPerformAssertions]
    public function test_parser_get_lexer_multiple_calls(): void
    {
        // First call creates lexer
        $this->parser->parse('/test/');

        // Second call should reuse lexer via getLexer
        $this->parser->parse('/another/');

        // Third call
        $this->parser->parse('/pattern/');
    }

    /**
     * Test Parser.isAtEnd() via patterns that check end of input
     * The isAtEnd method is called in checkLiteral and other places
     */
    #[DoesNotPerformAssertions]
    public function test_parser_is_at_end(): void
    {
        // Simple patterns that cause isAtEnd checks
        $this->parser->parse('/a/');
        $this->parser->parse('/ab/');
        $this->parser->parse('//'); // Empty pattern
    }

    /**
     * Test Lexer.consumeQuoteMode() with \Q...\E syntax
     * This triggers the private consumeQuoteMode() method
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_quote_mode(): void
    {
        // \Q...\E quote mode
        $this->parser->parse('/\Qtest\E/');
        $this->parser->parse('/\Qhello world\E/');
        $this->parser->parse('/\Q.*+?{}[]()\E/'); // Special chars in quote mode
        $this->parser->parse('/\Qunclosed/'); // Quote mode without \E
    }

    /**
     * Test Lexer.consumeCommentMode() with (?#...) syntax
     * This triggers the private consumeCommentMode() method
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_comment_mode_detailed(): void
    {
        // (?#...) comment mode
        $this->parser->parse('/(?#simple comment)/');
        $this->parser->parse('/(?#comment with spaces and punctuation!)/');
        $this->parser->parse('/a(?#comment)b/');
        $this->parser->parse('/(?#first)x(?#second)/');
        $this->parser->parse('/(?#)/'); // Empty comment
    }

    /**
     * Test Lexer.extractTokenValue() with various token types
     * This triggers the private extractTokenValue() method for different token types
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_extract_token_value_comprehensive(): void
    {
        // T_LITERAL_ESCAPED with special escapes
        $this->parser->parse('/\t/');  // Tab
        $this->parser->parse('/\n/');  // Newline
        $this->parser->parse('/\r/');  // Carriage return
        $this->parser->parse('/\f/');  // Form feed
        $this->parser->parse('/\v/');  // Vertical tab
        $this->parser->parse('/\e/');  // Escape
        $this->parser->parse('/\./');  // Escaped dot

        // T_PCRE_VERB
        $this->parser->parse('/(*FAIL)/');
        $this->parser->parse('/(*ACCEPT)/');
        $this->parser->parse('/(*COMMIT)/');

        // T_ASSERTION
        $this->parser->parse('/\b/');  // Word boundary
        $this->parser->parse('/\B/');  // Not word boundary
        $this->parser->parse('/\A/');  // Start of string
        $this->parser->parse('/\Z/');  // End of string
        $this->parser->parse('/\z/');  // Absolute end

        // T_CHAR_TYPE
        $this->parser->parse('/\d/');  // Digit
        $this->parser->parse('/\D/');  // Not digit
        $this->parser->parse('/\w/');  // Word char
        $this->parser->parse('/\W/');  // Not word char
        $this->parser->parse('/\s/');  // Whitespace
        $this->parser->parse('/\S/');  // Not whitespace

        // T_KEEP
        $this->parser->parse('/\K/');

        // T_BACKREF
        $this->parser->parse('/(a)\1/');
        $this->parser->parse('/(a)(b)\2/');

        // T_OCTAL_LEGACY
        $this->parser->parse('/\01/');
        $this->parser->parse('/\77/');

        // T_POSIX_CLASS
        $this->parser->parse('/[[:alnum:]]/');
        $this->parser->parse('/[[:alpha:]]/');
        $this->parser->parse('/[[:digit:]]/');
    }

    /**
     * Test Lexer directly to ensure __construct, reset, and tokenize are covered
     */
    public function test_lexer_direct_instantiation(): void
    {
        // Direct Lexer instantiation
        $lexer = new Lexer('test');
        $tokens = $lexer->tokenize();
        $this->assertIsArray($tokens);

        // Reset and tokenize again
        $lexer->reset('another');
        $tokens2 = $lexer->tokenize();
        $this->assertIsArray($tokens2);
    }
}
