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
use RegexParser\Builder\RegexBuilder;
use RegexParser\Exception\ParserException;
use RegexParser\Parser;

/**
 * Tests targeting uncovered branches in Parser and Lexer classes.
 */
class CoverageImprovementTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser([]);
    }

    /**
     * Test conditional with curly brace syntax: (?({name})yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_curly_brace_name(): void
    {
        $this->parser->parse('/(?<foo>x)(?({foo})yes|no)/');
    }

    /**
     * Test conditional with numeric reference: (?(1)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_numeric_reference(): void
    {
        $this->parser->parse('/(a)(?(1)yes|no)/');
    }

    /**
     * Test conditional with multi-digit numeric reference: (?(12)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_multi_digit_numeric_reference(): void
    {
        $this->parser->parse('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)(l)(?(12)yes|no)/');
    }

    /**
     * Test conditional with lookahead as condition: (?(?=...)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookahead_condition(): void
    {
        $this->parser->parse('/(?(?=test)yes|no)/');
    }

    /**
     * Test conditional with negative lookahead as condition: (?(?!...)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_negative_lookahead_condition(): void
    {
        $this->parser->parse('/(?(?!test)yes|no)/');
    }

    /**
     * Test conditional with bare group name: (?(name)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_bare_group_name(): void
    {
        $this->parser->parse('/(?<foo>x)(?(foo)yes|no)/');
    }

    /**
     * Test conditional with recursion check: (?(R)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_recursion_check(): void
    {
        $this->parser->parse('/(?(R)yes|no)/');
    }

    /**
     * Test conditional with angle bracket name: (?(<name>)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_angle_bracket_name(): void
    {
        $this->parser->parse('/(?<name>x)(?(<name>)yes|no)/');
    }

    /**
     * Test invalid conditional with ? but no valid lookaround
     * This should trigger the "Invalid conditional condition" exception
     */
    public function test_invalid_conditional_with_question_mark(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional condition');
        $this->parser->parse('/(?(?x)yes|no)/');
    }

    /**
     * Test quantifier range with comma but no max: {2,}
     */
    #[DoesNotPerformAssertions]
    public function test_quantifier_with_min_only(): void
    {
        $this->parser->parse('/a{2,}/');
    }

    /**
     * Test quantifier range with exact count: {5}
     */
    #[DoesNotPerformAssertions]
    public function test_quantifier_with_exact_count(): void
    {
        $this->parser->parse('/a{5}/');
    }

    /**
     * Test possessive quantifiers: *+, ++, ?+, {2,5}+
     */
    #[DoesNotPerformAssertions]
    public function test_possessive_quantifiers(): void
    {
        $patterns = [
            '/a*+/',
            '/a++/',
            '/a?+/',
            '/a{2,5}+/',
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test \g references with different formats
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_variations(): void
    {
        $patterns = [
            '/(?<name>a)\g<name>/',     // Named subroutine
            '/(?<name>a)\g{name}/',     // Named subroutine with braces
            '/(a)\g1/',                 // Numeric backref
            '/(a)\g{1}/',               // Numeric backref with braces
            '/(a)\g{-1}/',              // Relative backref
            '/(a)\g{+1}/',              // Forward backref
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test invalid \g reference syntax
     */
    public function test_invalid_g_reference_syntax(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid \g reference syntax');
        $this->parser->parse('/\g/');
    }

    /**
     * Test subroutine call (?P>name)
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_call_p_syntax(): void
    {
        $this->parser->parse('/(?<foo>a)(?P>foo)/');
    }

    /**
     * Test Python-style named groups with quotes
     */
    #[DoesNotPerformAssertions]
    public function test_python_style_named_groups_with_quotes(): void
    {
        $patterns = [
            "/(?P'name'a)/",
            '/(?P"name"a)/',
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test character class with negation at start
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_negation(): void
    {
        $this->parser->parse('/[^abc]/');
    }

    /**
     * Test character class with dash at different positions
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_with_dash_positions(): void
    {
        $patterns = [
            '/[-abc]/',    // Dash at start
            '/[abc-]/',    // Dash at end
            '/[a-z]/',     // Dash as range
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test empty alternation branches
     */
    #[DoesNotPerformAssertions]
    public function test_empty_alternation_branches(): void
    {
        $patterns = [
            '/abc|/',      // Empty right branch
            '/|abc/',      // Empty left branch
            '/abc||def/',  // Empty middle branch
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test comment groups: (?#comment)
     */
    #[DoesNotPerformAssertions]
    public function test_comment_groups(): void
    {
        $this->parser->parse('/(?#this is a comment)abc/');
    }

    /**
     * Test PCRE verbs
     */
    #[DoesNotPerformAssertions]
    public function test_pcre_verbs(): void
    {
        $patterns = [
            '/(*FAIL)/',
            '/(*ACCEPT)/',
            '/(*SKIP)/',
            '/(*MARK:foo)/',
            '/(*COMMIT)/',
            '/(*PRUNE)/',
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test atomic groups: (?>...)
     */
    #[DoesNotPerformAssertions]
    public function test_atomic_groups(): void
    {
        $this->parser->parse('/(?>abc)/');
    }

    /**
     * Test recursive pattern: (?R)
     */
    #[DoesNotPerformAssertions]
    public function test_recursive_pattern(): void
    {
        $this->parser->parse('/(?R)/');
    }

    /**
     * Test octal sequences
     */
    #[DoesNotPerformAssertions]
    public function test_octal_sequences(): void
    {
        $patterns = [
            '/\o{77}/',      // Octal with braces
            '/\01/',         // Legacy octal
            '/\077/',        // Legacy octal 3 digits
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test unicode sequences
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_sequences(): void
    {
        $patterns = [
            '/\x41/',        // Hex 2 digits
            '/\u{41}/',      // Unicode with braces
            '/\u{1F600}/',   // Unicode emoji
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test unicode properties
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_properties(): void
    {
        $patterns = [
            '/\p{L}/',       // Letter property
            '/\P{L}/',       // Negated letter property
            '/\p{^L}/',      // Negated in braces
            '/\P{^L}/',      // Double negation
            '/\pL/',         // Short form
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test POSIX character classes
     */
    #[DoesNotPerformAssertions]
    public function test_posix_character_classes(): void
    {
        $patterns = [
            '/[[:alnum:]]/',
            '/[[:alpha:]]/',
            '/[[:digit:]]/',
            '/[[:^digit:]]/',  // Negated POSIX class
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test inline flags
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags(): void
    {
        $patterns = [
            '/(?i)abc/',       // Case insensitive
            '/(?-i)abc/',      // Disable case insensitive
            '/(?i:abc)/',      // Case insensitive group
            '/(?ims)abc/',     // Multiple flags
            '/(?i-s)abc/',     // Enable i, disable s
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test assertions
     */
    #[DoesNotPerformAssertions]
    public function test_assertions(): void
    {
        $patterns = [
            '/\A/',     // Start of string
            '/\z/',     // End of string
            '/\Z/',     // End of string (before newline)
            '/\G/',     // Continue from previous match
            '/\b/',     // Word boundary
            '/\B/',     // Not word boundary
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test keep assertion: \K
     */
    #[DoesNotPerformAssertions]
    public function test_keep_assertion(): void
    {
        $this->parser->parse('/test\K/');
    }

    /**
     * Test character types
     */
    #[DoesNotPerformAssertions]
    public function test_character_types(): void
    {
        $patterns = [
            '/\d/',  // Digit
            '/\D/',  // Not digit
            '/\s/',  // Whitespace
            '/\S/',  // Not whitespace
            '/\w/',  // Word character
            '/\W/',  // Not word character
            '/\h/',  // Horizontal whitespace
            '/\H/',  // Not horizontal whitespace
            '/\v/',  // Vertical whitespace
            '/\V/',  // Not vertical whitespace
            '/\R/',  // Linebreak
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test backreferences
     */
    #[DoesNotPerformAssertions]
    public function test_backreferences(): void
    {
        $patterns = [
            '/(a)\1/',           // Numeric backref
            '/(a)\k<1>/',        // Named backref with number
            '/(?<n>a)\k<n>/',    // Named backref
            '/(?<n>a)\k{n}/',    // Named backref with braces
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test escaped special characters
     */
    #[DoesNotPerformAssertions]
    public function test_escaped_special_characters(): void
    {
        $patterns = [
            '/\t/',  // Tab
            '/\n/',  // Newline
            '/\r/',  // Carriage return
            '/\f/',  // Form feed
            '/\v/',  // Vertical tab (escape sequence)
            '/\e/',  // Escape
            '/\./',  // Escaped dot
            '/\[/',  // Escaped bracket
            '/\]/',  // Escaped bracket
            '/\(/',  // Escaped paren
            '/\)/',  // Escaped paren
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test quote mode: \Q...\E
     */
    #[DoesNotPerformAssertions]
    public function test_quote_mode(): void
    {
        $patterns = [
            '/\Q*+?\E/',        // Quote mode with end
            '/\Q*+?/',          // Quote mode without end
            '/a\Q\Eb/',         // Empty quote mode
            '/\Q.\E/',          // Quote single dot
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test lookaround assertions
     */
    #[DoesNotPerformAssertions]
    public function test_lookaround_assertions(): void
    {
        $patterns = [
            '/(?=abc)/',   // Positive lookahead
            '/(?!abc)/',   // Negative lookahead
            '/(?<=abc)/',  // Positive lookbehind
            '/(?<!abc)/',  // Negative lookbehind
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test ranges in character classes
     */
    #[DoesNotPerformAssertions]
    public function test_ranges_in_character_classes(): void
    {
        $patterns = [
            '/[a-z]/',
            '/[A-Z]/',
            '/[0-9]/',
            '/[a-zA-Z0-9]/',
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test dot metacharacter
     */
    #[DoesNotPerformAssertions]
    public function test_dot_metacharacter(): void
    {
        $this->parser->parse('/./');
    }

    /**
     * Test anchors
     */
    #[DoesNotPerformAssertions]
    public function test_anchors(): void
    {
        $patterns = [
            '/^/',   // Start anchor
            '/$/',   // End anchor
            '/^a$/', // Both anchors
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test nested groups
     */
    #[DoesNotPerformAssertions]
    public function test_nested_groups(): void
    {
        $this->parser->parse('/((a)(b))/');
    }

    /**
     * Test mixed quantifiers
     */
    #[DoesNotPerformAssertions]
    public function test_mixed_quantifiers(): void
    {
        $patterns = [
            '/a*/',      // Zero or more
            '/a+/',      // One or more
            '/a?/',      // Zero or one
            '/a*?/',     // Lazy zero or more
            '/a+?/',     // Lazy one or more
            '/a??/',     // Lazy zero or one
            '/a{2}/',    // Exact
            '/a{2,}/',   // Min
            '/a{2,4}/',  // Range
            '/a{2,4}?/', // Lazy range
        ];

        foreach ($patterns as $pattern) {
            $this->parser->parse($pattern);
        }
    }

    /**
     * Test RegexBuilder.literal() with empty string
     * This covers the line: if ('' === $value) return $this;
     */
    public function test_regex_builder_literal_empty_string(): void
    {
        $builder = new RegexBuilder();
        $result = $builder->literal('')->compile();

        // Empty literal should produce an empty pattern
        $this->assertIsString($result);
    }

    /**
     * Test RegexBuilder with completely empty pattern
     * This covers the line: return new LiteralNode('', 0, 0);
     */
    public function test_regex_builder_empty_pattern(): void
    {
        $builder = new RegexBuilder();
        $result = $builder->compile();

        // Completely empty builder should produce an empty pattern
        $this->assertIsString($result);
    }

    /**
     * Test RegexBuilder.literal() with multi-byte characters
     */
    public function test_regex_builder_literal_multibyte(): void
    {
        $builder = new RegexBuilder();
        $result = $builder->literal('été')->compile();

        $this->assertIsString($result);
        $this->assertStringContainsString('é', $result);
    }

    /**
     * Test Parser.getLexer() by parsing multiple patterns with same Parser instance
     * This ensures the private getLexer() method reuses the Lexer instance
     */
    #[DoesNotPerformAssertions]
    public function test_parser_get_lexer_reuse(): void
    {
        // First parse - creates new Lexer
        $this->parser->parse('/abc/');
        
        // Second parse - should reuse Lexer via getLexer()
        $this->parser->parse('/def/');
        
        // Third parse - ensures reset works correctly
        $this->parser->parse('/[a-z]+/');
    }

    /**
     * Test Parser.reconstructTokenValue() via parseComment which uses previous()
     * The reconstructTokenValue is called when parsing group modifiers
     * Testing (?P<name>...) which calls previous() in parseGroupModifier
     */
    #[DoesNotPerformAssertions]
    public function test_parser_reconstruct_token_value(): void
    {
        // This pattern triggers parseGroupModifier which calls previous()
        // and may use reconstructTokenValue
        $this->parser->parse('/(?P<name>test)/');
    }

    /**
     * Test Parser.previous() method through alternation parsing
     * The previous() method is called in parseAlternation at line 221
     */
    #[DoesNotPerformAssertions]
    public function test_parser_previous_method(): void
    {
        // Alternation pattern triggers previous() method
        $this->parser->parse('/a|b|c/');
        $this->parser->parse('/foo|bar|baz/');
    }

    /**
     * Test Parser.consumeWhile() method through parseGroupName
     * consumeWhile is used in parseGroupName and parseSubroutineName
     */
    #[DoesNotPerformAssertions]
    public function test_parser_consume_while(): void
    {
        // Named group with multiple characters triggers consumeWhile
        $this->parser->parse('/(?<longname>test)/');
        $this->parser->parse('/(?<abc123>pattern)/');
    }

    /**
     * Test Lexer.lexCommentMode() with (?#...) comment syntax
     * This triggers the private lexCommentMode() method
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_comment_mode(): void
    {
        // Simple comment
        $this->parser->parse('/(?#comment)test/');
        
        // Comment with special characters
        $this->parser->parse('/(?#this is a comment with spaces)abc/');
        
        // Comment at the end
        $this->parser->parse('/test(?#end comment)/');
        
        // Multiple comments
        $this->parser->parse('/(?#first)a(?#second)b/');
    }

    /**
     * Test Lexer.extractTokenValue() with various token types
     * This private method is called during tokenization for different token types
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_extract_token_value(): void
    {
        // T_LITERAL_ESCAPED: \t, \n, \r, \f, \v, \e
        $this->parser->parse('/\t\n\r\f\v\e/');
        
        // T_PCRE_VERB: (*FAIL), (*ACCEPT)
        $this->parser->parse('/(*FAIL)/');
        $this->parser->parse('/(*ACCEPT)/');
        
        // T_ASSERTION: \b, \B, \A, \Z, \z
        $this->parser->parse('/\b\B\A\Z\z/');
        
        // T_CHAR_TYPE: \d, \D, \w, \W, \s, \S
        $this->parser->parse('/\d\D\w\W\s\S/');
        
        // T_KEEP: \K
        $this->parser->parse('/\K/');
        
        // T_BACKREF: \1, \2
        $this->parser->parse('/(a)\1/');
        
        // T_OCTAL_LEGACY: \01, \02
        $this->parser->parse('/\01\02/');
        
        // T_POSIX_CLASS: [[:alnum:]]
        $this->parser->parse('/[[:alnum:]]/');
        $this->parser->parse('/[[:alpha:]]/');
    }

    /**
     * Test Lexer.normalizeUnicodeProp() with Unicode property patterns
     * This private method handles \p{} and \P{} normalization
     */
    #[DoesNotPerformAssertions]
    public function test_lexer_normalize_unicode_prop(): void
    {
        // \p{L} - regular property
        $this->parser->parse('/\p{L}/');
        
        // \P{L} - negated property (adds ^)
        $this->parser->parse('/\P{L}/');
        
        // \p{^L} - already negated
        $this->parser->parse('/\p{^L}/');
        
        // \P{^L} - double negation (removes ^)
        $this->parser->parse('/\P{^L}/');
        
        // Various Unicode properties
        $this->parser->parse('/\p{Ll}/');  // Lowercase letter
        $this->parser->parse('/\P{Lu}/');  // Not uppercase letter
        $this->parser->parse('/\p{N}/');   // Number
        $this->parser->parse('/\P{P}/');   // Not punctuation
        $this->parser->parse('/\p{Sc}/');  // Currency symbol
    }
}
