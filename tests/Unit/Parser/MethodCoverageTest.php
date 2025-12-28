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
use RegexParser\Exception\ParserException;
use RegexParser\Exception\RecursionLimitException;
use RegexParser\Exception\SyntaxErrorException;
use RegexParser\Parser;
use RegexParser\Regex;
use RegexParser\Tests\TestUtils\ParserAccessor;
use RegexParser\Token;
use RegexParser\TokenType;

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

    /**
     * Test Parser.parseAtom() T_QUOTE_MODE_START path
     * This triggers the quote mode handling in parseAtom
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_atom_quote_mode_start(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens with T_QUOTE_MODE_START
        $tokens = [
            new Token(TokenType::T_QUOTE_MODE_START, '\Q', 0),
            new Token(TokenType::T_LITERAL, 'test', 2),
            new Token(TokenType::T_EOF, '', 6),
        ];
        $accessor->setTokens($tokens);

        // Call parseAtom which should handle T_QUOTE_MODE_START
        $accessor->callPrivateMethod('parseAtom');
    }

    /**
     * Test Parser.parseAtom() T_QUOTE_MODE_END path
     * This triggers the quote mode end handling in parseAtom
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_atom_quote_mode_end(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens with T_QUOTE_MODE_END
        $tokens = [
            new Token(TokenType::T_QUOTE_MODE_END, '\E', 0),
            new Token(TokenType::T_LITERAL, 'test', 2),
            new Token(TokenType::T_EOF, '', 6),
        ];
        $accessor->setTokens($tokens);

        // Call parseAtom which should handle T_QUOTE_MODE_END
        $accessor->callPrivateMethod('parseAtom');
    }

    /**
     * Test Parser.parseCallout() invalid argument path
     * This triggers the else clause that throws for invalid callout arguments
     */
    public function test_parser_parse_callout_invalid_argument(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens with T_CALLOUT having invalid value
        $tokens = [
            new Token(TokenType::T_CALLOUT, '@invalid', 0),
            new Token(TokenType::T_EOF, '', 8),
        ];
        $accessor->setTokens($tokens);

        // Advance to make the T_CALLOUT the previous token
        $accessor->advance();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid callout argument: @invalid at position 0');

        // Call parseCallout which should throw for invalid argument
        $accessor->callPrivateMethod('parseCallout');
    }

    /**
     * Test Parser.createCharLiteralNodeFromToken() unsupported type path
     * This triggers the default case that throws InvalidArgumentException
     */
    public function test_parser_create_char_literal_unsupported_type(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        $token = new Token(TokenType::T_LITERAL, 'test', 0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported character literal token type.');

        // Call createCharLiteralNodeFromToken with unsupported type
        $accessor->callPrivateMethod('createCharLiteralNodeFromToken', [$token, TokenType::T_LITERAL, 0]);
    }

    /**
     * Test Parser.parseUnicodeCodePoint() \x hex parsing path
     * This triggers the first if condition for \xXX format
     */
    public function test_parser_parse_unicode_code_point_hex(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Call parseUnicodeCodePoint with \xFF format
        $result = $accessor->callPrivateMethod('parseUnicodeCodePoint', ['\\xFF']);

        $this->assertSame(255, $result);
    }

    /**
     * Test Parser.parsePcreVerbInGroup() method with arguments
     * This triggers the literal * matching path in parseGroupModifier and argument parsing
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_pcre_verb_in_group_with_args(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for (?(*MARK:name)expr)
        // T_GROUP_MODIFIER_OPEN, T_LITERAL('*'), T_LITERAL('M'), T_LITERAL('A'), T_LITERAL('R'), T_LITERAL('K'), T_LITERAL(':'), T_LITERAL('n'), T_LITERAL('a'), T_LITERAL('m'), T_LITERAL('e'), T_GROUP_CLOSE, T_LITERAL('e'), T_LITERAL('x'), T_LITERAL('p'), T_LITERAL('r'), T_EOF
        $tokens = [
            new Token(TokenType::T_GROUP_MODIFIER_OPEN, '(?', 0),
            new Token(TokenType::T_LITERAL, '*', 2),
            new Token(TokenType::T_LITERAL, 'M', 3),
            new Token(TokenType::T_LITERAL, 'A', 4),
            new Token(TokenType::T_LITERAL, 'R', 5),
            new Token(TokenType::T_LITERAL, 'K', 6),
            new Token(TokenType::T_LITERAL, ':', 7),
            new Token(TokenType::T_LITERAL, 'n', 8),
            new Token(TokenType::T_LITERAL, 'a', 9),
            new Token(TokenType::T_LITERAL, 'm', 10),
            new Token(TokenType::T_LITERAL, 'e', 11),
            new Token(TokenType::T_GROUP_CLOSE, ')', 12),
            new Token(TokenType::T_LITERAL, 'e', 13),
            new Token(TokenType::T_LITERAL, 'x', 14),
            new Token(TokenType::T_LITERAL, 'p', 15),
            new Token(TokenType::T_LITERAL, 'r', 16),
            new Token(TokenType::T_EOF, '', 17),
        ];
        $accessor->setTokens($tokens);

        // Advance past the (? to set up for parseGroupModifier
        $accessor->advance();

        // Call parseGroupModifier which should match * and call parsePcreVerbInGroup with arguments
        $accessor->callPrivateMethod('parseGroupModifier');
    }

    /**
     * Test Parser.guardRecursionDepth() by exceeding recursion limit
     * This triggers the recursion limit exception
     */
    public function test_parser_guard_recursion_depth(): void
    {
        // Create a deeply nested regex that exceeds the limit
        // This will cause parseAlternation -> parseSequence -> parseQuantifiedAtom -> parseAtom -> parseGroupOrCharClassAtom -> parseGroupModifier -> parseConditional -> parseAlternation (recursive)
        $nestedRegex = str_repeat('(', 5).'a'.str_repeat(')', 5);

        $this->expectException(RecursionLimitException::class);
        $this->expectExceptionMessage('Recursion limit of 3 exceeded');

        // Parse the deeply nested regex with low recursion limit
        Regex::create(['max_recursion_depth' => 3])->parse($nestedRegex);
    }

    /**
     * Test Parser.parseCharClassPart() T_CONTROL_CHAR path
     * This triggers the control character handling in character classes
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_char_class_part_control_char(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for [\cA]
        // T_CHAR_CLASS_OPEN, T_CONTROL_CHAR('A'), T_CHAR_CLASS_CLOSE, T_EOF
        $tokens = [
            new Token(TokenType::T_CHAR_CLASS_OPEN, '[', 0),
            new Token(TokenType::T_CONTROL_CHAR, 'A', 1),
            new Token(TokenType::T_CHAR_CLASS_CLOSE, ']', 3),
            new Token(TokenType::T_EOF, '', 4),
        ];
        $accessor->setTokens($tokens);

        // Advance to the T_CONTROL_CHAR token
        $accessor->advance();

        // Call parseCharClassPart which should handle T_CONTROL_CHAR
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseCharClassPart() unexpected token path
     * This triggers the else clause that throws for invalid tokens in character class
     */
    public function test_parser_parse_char_class_part_unexpected_token(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens with an unexpected token in char class
        // T_CHAR_CLASS_OPEN, T_ANCHOR('^'), T_CHAR_CLASS_CLOSE, T_EOF
        $tokens = [
            new Token(TokenType::T_CHAR_CLASS_OPEN, '[', 0),
            new Token(TokenType::T_ANCHOR, '^', 1),
            new Token(TokenType::T_CHAR_CLASS_CLOSE, ']', 2),
            new Token(TokenType::T_EOF, '', 3),
        ];
        $accessor->setTokens($tokens);

        // Advance to the T_ANCHOR token
        $accessor->advance();

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token "^" in character class at position 1');

        // Call parseCharClassPart which should throw for unexpected token
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseCharClassPart() range with T_UNICODE_PROP end
     * This triggers the Unicode property handling in character class range end
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_char_class_part_range_unicode_prop(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for [a-\pL]
        // T_LITERAL('a'), T_RANGE, T_UNICODE_PROP('L'), T_EOF
        $tokens = [
            new Token(TokenType::T_LITERAL, 'a', 0),
            new Token(TokenType::T_RANGE, '-', 1),
            new Token(TokenType::T_UNICODE_PROP, 'L', 2),
            new Token(TokenType::T_EOF, '', 4),
        ];
        $accessor->setTokens($tokens);

        // Call parseCharClassPart which should handle range with Unicode prop end
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseCharClassPart() range with T_CONTROL_CHAR end
     * This triggers the control character handling in character class range end
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_char_class_part_range_control_char(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for [a-\cA]
        // T_LITERAL('a'), T_RANGE, T_CONTROL_CHAR('A'), T_EOF
        $tokens = [
            new Token(TokenType::T_LITERAL, 'a', 0),
            new Token(TokenType::T_RANGE, '-', 1),
            new Token(TokenType::T_CONTROL_CHAR, 'A', 2),
            new Token(TokenType::T_EOF, '', 5),
        ];
        $accessor->setTokens($tokens);

        // Call parseCharClassPart which should handle range with control char end
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseCharClassPart() range with T_OCTAL end
     * This triggers the octal handling in character class range end
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_char_class_part_range_octal(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for [a-\12]
        // T_LITERAL('a'), T_RANGE, T_OCTAL('12'), T_EOF
        $tokens = [
            new Token(TokenType::T_LITERAL, 'a', 0),
            new Token(TokenType::T_RANGE, '-', 1),
            new Token(TokenType::T_OCTAL, '12', 2),
            new Token(TokenType::T_EOF, '', 5),
        ];
        $accessor->setTokens($tokens);

        // Call parseCharClassPart which should handle range with octal end
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseCharClassPart() range with T_OCTAL_LEGACY end
     * This triggers the legacy octal handling in character class range end
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_char_class_part_range_octal_legacy(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for [a-\12] (legacy octal)
        // T_LITERAL('a'), T_RANGE, T_OCTAL_LEGACY('12'), T_EOF
        $tokens = [
            new Token(TokenType::T_LITERAL, 'a', 0),
            new Token(TokenType::T_RANGE, '-', 1),
            new Token(TokenType::T_OCTAL_LEGACY, '12', 2),
            new Token(TokenType::T_EOF, '', 5),
        ];
        $accessor->setTokens($tokens);

        // Call parseCharClassPart which should handle range with legacy octal end
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseCharClassPart() range with T_POSIX_CLASS end
     * This triggers the POSIX class handling in character class range end
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_char_class_part_range_posix_class(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for [a-[:alnum:]]
        // T_LITERAL('a'), T_RANGE, T_POSIX_CLASS('alnum'), T_EOF
        $tokens = [
            new Token(TokenType::T_LITERAL, 'a', 0),
            new Token(TokenType::T_RANGE, '-', 1),
            new Token(TokenType::T_POSIX_CLASS, 'alnum', 2),
            new Token(TokenType::T_EOF, '', 9),
        ];
        $accessor->setTokens($tokens);

        // Call parseCharClassPart which should handle range with POSIX class end
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseCharClassPart() range with unexpected token end
     * This triggers the else clause that throws for invalid tokens in character class range
     */
    public function test_parser_parse_char_class_part_range_unexpected_token(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for [a-^] (invalid range end)
        // T_LITERAL('a'), T_RANGE, T_ANCHOR('^'), T_EOF
        $tokens = [
            new Token(TokenType::T_LITERAL, 'a', 0),
            new Token(TokenType::T_RANGE, '-', 1),
            new Token(TokenType::T_ANCHOR, '^', 2),
            new Token(TokenType::T_EOF, '', 3),
        ];
        $accessor->setTokens($tokens);

        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token "^" in character class range at position 2');

        // Call parseCharClassPart which should throw for unexpected token in range
        $accessor->callPrivateMethod('parseCharClassPart');
    }

    /**
     * Test Parser.parseNamedUnicodeCodePoint() invalid format
     * This triggers the preg_match failure path that returns -1
     */
    public function test_parser_parse_named_unicode_code_point_invalid(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Call parseNamedUnicodeCodePoint with invalid format
        $result = $accessor->callPrivateMethod('parseNamedUnicodeCodePoint', ['invalid']);

        $this->assertSame(-1, $result);
    }

    /**
     * Test Parser.parseControlCharCodePoint() empty char
     * This triggers the empty string check that returns -1
     */
    public function test_parser_parse_control_char_code_point_empty(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Call parseControlCharCodePoint with empty string
        $result = $accessor->callPrivateMethod('parseControlCharCodePoint', ['']);

        $this->assertSame(-1, $result);
    }

    /**
     * Test Parser.parseGroupModifier() rewind 'R' path
     * This triggers the stream rewind when (?R is not followed by )
     */
    public function test_parser_parse_group_modifier_rewind_r(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for (?Rabc) invalid
        // T_GROUP_MODIFIER_OPEN, T_LITERAL('R'), T_LITERAL('a'), T_LITERAL('b'), T_LITERAL('c'), T_GROUP_CLOSE, T_EOF
        $tokens = [
            new Token(TokenType::T_GROUP_MODIFIER_OPEN, '(?', 0),
            new Token(TokenType::T_LITERAL, 'R', 2),
            new Token(TokenType::T_LITERAL, 'a', 3),
            new Token(TokenType::T_LITERAL, 'b', 4),
            new Token(TokenType::T_LITERAL, 'c', 5),
            new Token(TokenType::T_GROUP_CLOSE, ')', 6),
            new Token(TokenType::T_EOF, '', 7),
        ];
        $accessor->setTokens($tokens);

        // Advance past the (? to set up for parseGroupModifier
        $accessor->advance();

        // Expect exception because after rewind, no valid modifier is found
        $this->expectException(SyntaxErrorException::class);
        $this->expectExceptionMessage('Invalid group modifier syntax');

        // Call parseGroupModifier which should match 'R', not find ), rewind, then fail
        $accessor->callPrivateMethod('parseGroupModifier');
    }

    /**
     * Test Parser.parsePcreVerbInGroup() method with arguments and end of pattern
     * This triggers the literal * matching path, argument parsing, and empty expression after verb
     */
    #[DoesNotPerformAssertions]
    public function test_parser_parse_pcre_verb_in_group_with_args_end(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for (?(*MARK:name)) at end of pattern
        // T_GROUP_MODIFIER_OPEN, T_LITERAL('*'), T_LITERAL('M'), T_LITERAL('A'), T_LITERAL('R'), T_LITERAL('K'), T_LITERAL(':'), T_LITERAL('n'), T_LITERAL('a'), T_LITERAL('m'), T_LITERAL('e'), T_GROUP_CLOSE, T_EOF
        $tokens = [
            new Token(TokenType::T_GROUP_MODIFIER_OPEN, '(?', 0),
            new Token(TokenType::T_LITERAL, '*', 2),
            new Token(TokenType::T_LITERAL, 'M', 3),
            new Token(TokenType::T_LITERAL, 'A', 4),
            new Token(TokenType::T_LITERAL, 'R', 5),
            new Token(TokenType::T_LITERAL, 'K', 6),
            new Token(TokenType::T_LITERAL, ':', 7),
            new Token(TokenType::T_LITERAL, 'n', 8),
            new Token(TokenType::T_LITERAL, 'a', 9),
            new Token(TokenType::T_LITERAL, 'm', 10),
            new Token(TokenType::T_LITERAL, 'e', 11),
            new Token(TokenType::T_GROUP_CLOSE, ')', 12),
            new Token(TokenType::T_EOF, '', 13),
        ];
        $accessor->setTokens($tokens);

        // Advance past the (? to set up for parseGroupModifier
        $accessor->advance();

        // Call parseGroupModifier which should match * and call parsePcreVerbInGroup with arguments and no following expression
        $accessor->callPrivateMethod('parseGroupModifier');
    }

    /**
     * Test Parser.parsePcreVerbInGroup() verb name collection break
     * This triggers the break in verb name collection when encountering non-literal token
     */
    public function test_parser_parse_pcre_verb_in_group_verb_break(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for (?(*TEST^)expr) where ^ breaks verb collection
        // T_GROUP_MODIFIER_OPEN, T_LITERAL('*'), T_LITERAL('T'), T_LITERAL('E'), T_LITERAL('S'), T_LITERAL('T'), T_ANCHOR('^'), T_GROUP_CLOSE, T_LITERAL('e'), T_EOF
        $tokens = [
            new Token(TokenType::T_GROUP_MODIFIER_OPEN, '(?', 0),
            new Token(TokenType::T_LITERAL, '*', 2),
            new Token(TokenType::T_LITERAL, 'T', 3),
            new Token(TokenType::T_LITERAL, 'E', 4),
            new Token(TokenType::T_LITERAL, 'S', 5),
            new Token(TokenType::T_LITERAL, 'T', 6),
            new Token(TokenType::T_ANCHOR, '^', 7),  // Non-literal token breaks verb collection
            new Token(TokenType::T_GROUP_CLOSE, ')', 8),
            new Token(TokenType::T_LITERAL, 'e', 9),
            new Token(TokenType::T_EOF, '', 10),
        ];
        $accessor->setTokens($tokens);

        // Advance past the (? to set up for parseGroupModifier
        $accessor->advance();

        // Expect exception because after break, consume expects ) but finds ^
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected ) to close PCRE verb');

        // Call parseGroupModifier which should match * and call parsePcreVerbInGroup with verb collection break
        $accessor->callPrivateMethod('parseGroupModifier');
    }

    /**
     * Test Parser.parsePcreVerbInGroup() argument collection break
     * This triggers the break in argument collection when encountering non-literal token
     */
    public function test_parser_parse_pcre_verb_in_group_arg_break(): void
    {
        $parser = new Parser();
        $accessor = new ParserAccessor($parser);

        // Create tokens for (?(*MARK:name^)expr) where ^ breaks argument collection
        // T_GROUP_MODIFIER_OPEN, T_LITERAL('*'), T_LITERAL('M'), T_LITERAL('A'), T_LITERAL('R'), T_LITERAL('K'), T_LITERAL(':'), T_LITERAL('n'), T_LITERAL('a'), T_LITERAL('m'), T_LITERAL('e'), T_ANCHOR('^'), T_GROUP_CLOSE, T_LITERAL('e'), T_EOF
        $tokens = [
            new Token(TokenType::T_GROUP_MODIFIER_OPEN, '(?', 0),
            new Token(TokenType::T_LITERAL, '*', 2),
            new Token(TokenType::T_LITERAL, 'M', 3),
            new Token(TokenType::T_LITERAL, 'A', 4),
            new Token(TokenType::T_LITERAL, 'R', 5),
            new Token(TokenType::T_LITERAL, 'K', 6),
            new Token(TokenType::T_LITERAL, ':', 7),
            new Token(TokenType::T_LITERAL, 'n', 8),
            new Token(TokenType::T_LITERAL, 'a', 9),
            new Token(TokenType::T_LITERAL, 'm', 10),
            new Token(TokenType::T_LITERAL, 'e', 11),
            new Token(TokenType::T_ANCHOR, '^', 12),  // Non-literal token breaks argument collection
            new Token(TokenType::T_GROUP_CLOSE, ')', 13),
            new Token(TokenType::T_LITERAL, 'e', 14),
            new Token(TokenType::T_EOF, '', 15),
        ];
        $accessor->setTokens($tokens);

        // Advance past the (? to set up for parseGroupModifier
        $accessor->advance();

        // Expect exception because after break, consume expects ) but finds ^
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected ) to close PCRE verb');

        // Call parseGroupModifier which should match * and call parsePcreVerbInGroup with argument collection break
        $accessor->callPrivateMethod('parseGroupModifier');
    }
}
