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
    public function test_conditional_with_curly_brace_name(): void
    {
        $this->parser->parse('/(?<foo>x)(?({foo})yes|no)/');
    }

    /**
     * Test conditional with numeric reference: (?(1)yes|no)
     */
    public function test_conditional_with_numeric_reference(): void
    {
        $this->parser->parse('/(a)(?(1)yes|no)/');
    }

    /**
     * Test conditional with multi-digit numeric reference: (?(12)yes|no)
     */
    public function test_conditional_with_multi_digit_numeric_reference(): void
    {
        $this->parser->parse('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(k)(l)(?(12)yes|no)/');
    }

    /**
     * Test conditional with lookahead as condition: (?(?=...)yes|no)
     */
    public function test_conditional_with_lookahead_condition(): void
    {
        $this->parser->parse('/(?(?=test)yes|no)/');
    }

    /**
     * Test conditional with negative lookahead as condition: (?(?!...)yes|no)
     */
    public function test_conditional_with_negative_lookahead_condition(): void
    {
        $this->parser->parse('/(?(?!test)yes|no)/');
    }

    /**
     * Test conditional with bare group name: (?(name)yes|no)
     */
    public function test_conditional_with_bare_group_name(): void
    {
        $this->parser->parse('/(?<foo>x)(?(foo)yes|no)/');
    }

    /**
     * Test conditional with recursion check: (?(R)yes|no)
     */
    public function test_conditional_with_recursion_check(): void
    {
        $this->parser->parse('/(?(R)yes|no)/');
    }

    /**
     * Test conditional with angle bracket name: (?(<name>)yes|no)
     */
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
    public function test_quantifier_with_min_only(): void
    {
        $this->parser->parse('/a{2,}/');
    }

    /**
     * Test quantifier range with exact count: {5}
     */
    public function test_quantifier_with_exact_count(): void
    {
        $this->parser->parse('/a{5}/');
    }

    /**
     * Test possessive quantifiers: *+, ++, ?+, {2,5}+
     */
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
    public function test_subroutine_call_p_syntax(): void
    {
        $this->parser->parse('/(?<foo>a)(?P>foo)/');
    }

    /**
     * Test Python-style named groups with quotes
     */
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
    public function test_char_class_negation(): void
    {
        $this->parser->parse('/[^abc]/');
    }

    /**
     * Test character class with dash at different positions
     */
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
    public function test_comment_groups(): void
    {
        $this->parser->parse('/(?#this is a comment)abc/');
    }

    /**
     * Test PCRE verbs
     */
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
    public function test_atomic_groups(): void
    {
        $this->parser->parse('/(?>abc)/');
    }

    /**
     * Test recursive pattern: (?R)
     */
    public function test_recursive_pattern(): void
    {
        $this->parser->parse('/(?R)/');
    }

    /**
     * Test octal sequences
     */
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
    public function test_keep_assertion(): void
    {
        $this->parser->parse('/test\K/');
    }

    /**
     * Test character types
     */
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
    public function test_dot_metacharacter(): void
    {
        $this->parser->parse('/./');
    }

    /**
     * Test anchors
     */
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
    public function test_nested_groups(): void
    {
        $this->parser->parse('/((a)(b))/');
    }

    /**
     * Test mixed quantifiers
     */
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
}
