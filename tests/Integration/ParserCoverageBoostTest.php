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
 * Tests to improve code coverage for the Parser class.
 * Specifically targeting uncovered parsing branches and edge cases.
 */
class ParserCoverageBoostTest extends TestCase
{
    /**
     * Test Python-style named groups with single quotes.
     */
    public function test_python_named_group_single_quotes(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse("/(?P'name'test)/");

        $this->assertNotNull($ast);
    }

    /**
     * Test Python-style named groups with double quotes.
     */
    public function test_python_named_group_double_quotes(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?P"name"test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test Python-style named group with angle brackets.
     */
    public function test_python_named_group_angle_brackets(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?P<name>test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test Python-style subroutine call.
     */
    public function test_python_subroutine_call(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?P<name>test)(?P>name)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test Python-style backref (should throw exception as not supported).
     */
    public function test_python_backref_not_supported(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreferences (?P=name) are not supported yet');

        $parser = new Parser([]);
        $parser->parse('/(?P<name>test)(?P=name)/');
    }

    /**
     * Test positive lookbehind assertion.
     */
    public function test_positive_lookbehind(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<=test)abc/');

        $this->assertNotNull($ast);
    }

    /**
     * Test negative lookbehind assertion.
     */
    public function test_negative_lookbehind(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<!test)abc/');

        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with number.
     */
    public function test_conditional_with_number(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(test)(?(1)yes|no)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with named group reference.
     */
    public function test_conditional_with_named_group(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<name>test)(?(<name>)yes|no)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with assertion as condition.
     */
    public function test_conditional_with_lookahead_positive(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?((?=test))yes|no)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with negative lookahead as condition.
     */
    public function test_conditional_with_lookahead_negative(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?((?!test))yes|no)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with positive lookbehind as condition.
     */
    public function test_conditional_with_lookbehind_positive(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?((?<=test))yes|no)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with negative lookbehind as condition.
     */
    public function test_conditional_with_lookbehind_negative(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?((?<!test))yes|no)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test conditional without else branch.
     */
    public function test_conditional_without_else(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(test)(?(1)yes)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test subroutine call with various formats.
     */
    public function test_subroutine_call_by_number(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(test)(?1)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test subroutine call with relative reference.
     */
    public function test_subroutine_call_relative(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(test)(?-1)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test subroutine call with named reference.
     */
    public function test_subroutine_call_named(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<name>test)(?&name)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test atomic group.
     */
    public function test_atomic_group(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?>test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test non-capturing group.
     */
    public function test_non_capturing_group(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?:test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test group with flags.
     */
    public function test_group_with_flags_i(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?i:test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test group with multiple flags.
     */
    public function test_group_with_multiple_flags(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?im:test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test group with negative flags.
     */
    public function test_group_with_negative_flags(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?-i:test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test group with mixed flags.
     */
    public function test_group_with_mixed_flags(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?i-m:test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test character class with range.
     */
    public function test_char_class_with_range(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/[a-z]/');

        $this->assertNotNull($ast);
    }

    /**
     * Test character class with multiple ranges.
     */
    public function test_char_class_with_multiple_ranges(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/[a-zA-Z0-9]/');

        $this->assertNotNull($ast);
    }

    /**
     * Test negated character class.
     */
    public function test_negated_char_class(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/[^a-z]/');

        $this->assertNotNull($ast);
    }

    /**
     * Test POSIX character class.
     */
    public function test_posix_char_class(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/[[:alnum:]]/');

        $this->assertNotNull($ast);
    }

    /**
     * Test negated POSIX character class.
     */
    public function test_negated_posix_char_class(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/[[:^alnum:]]/');

        $this->assertNotNull($ast);
    }

    /**
     * Test octal escape sequences.
     */
    public function test_octal_legacy(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/\101/');

        $this->assertNotNull($ast);
    }

    /**
     * Test modern octal notation.
     */
    public function test_octal_modern(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/\o{101}/');

        $this->assertNotNull($ast);
    }

    /**
     * Test Unicode escape sequences.
     */
    public function test_unicode_escape_hex(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/\x41/');

        $this->assertNotNull($ast);
    }

    /**
     * Test Unicode escape with braces.
     */
    public function test_unicode_escape_braces(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/\u{0041}/');

        $this->assertNotNull($ast);
    }

    /**
     * Test PCRE verbs.
     */
    public function test_pcre_verb_fail(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(*FAIL)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test PCRE verb with name.
     */
    public function test_pcre_verb_mark(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(*MARK:test)/');

        $this->assertNotNull($ast);
    }

    /**
     * Test keep \K.
     */
    public function test_keep(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/test\K/');

        $this->assertNotNull($ast);
    }

    /**
     * Test various anchors.
     */
    public function test_anchors(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/^test/',    // Start anchor
            '/test$/',    // End anchor
            '/^test$/',   // Both anchors
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $this->assertNotNull($ast);
        }
    }

    /**
     * Test various assertions.
     */
    public function test_assertions(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/\A/',      // Start of string
            '/\z/',      // End of string
            '/\Z/',      // End of string or before newline
            '/\G/',      // Continuing match
            '/\b/',      // Word boundary
            '/\B/',      // Not word boundary
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $this->assertNotNull($ast);
        }
    }

    /**
     * Test character types.
     */
    public function test_char_types(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/\d/',      // Digit
            '/\D/',      // Not digit
            '/\s/',      // Whitespace
            '/\S/',      // Not whitespace
            '/\w/',      // Word character
            '/\W/',      // Not word character
            '/\h/',      // Horizontal whitespace
            '/\H/',      // Not horizontal whitespace
            '/\v/',      // Vertical whitespace
            '/\V/',      // Not vertical whitespace
            '/\R/',      // Line break
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $this->assertNotNull($ast);
        }
    }

    /**
     * Test backref with number.
     */
    public function test_backref_numbered(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(test)\1/');

        $this->assertNotNull($ast);
    }

    /**
     * Test backref with k<name>.
     */
    public function test_backref_named_k_angle(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<name>test)\k<name>/');

        $this->assertNotNull($ast);
    }

    /**
     * Test backref with k{name}.
     */
    public function test_backref_named_k_brace(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<name>test)\k{name}/');

        $this->assertNotNull($ast);
    }

    /**
     * Test \g reference with number.
     */
    public function test_g_reference_number(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(test)\g1/');

        $this->assertNotNull($ast);
    }

    /**
     * Test \g reference with relative number.
     */
    public function test_g_reference_relative(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(test)\g-1/');

        $this->assertNotNull($ast);
    }

    /**
     * Test \g reference with angle brackets.
     */
    public function test_g_reference_angle(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<name>test)\g<name>/');

        $this->assertNotNull($ast);
    }

    /**
     * Test \g reference with braces.
     */
    public function test_g_reference_brace(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?<name>test)\g{name}/');

        $this->assertNotNull($ast);
    }

    /**
     * Test dot (matches any character).
     */
    public function test_dot(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/./');

        $this->assertNotNull($ast);
    }

    /**
     * Test quantifiers.
     */
    public function test_quantifiers(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/a*/',      // Zero or more
            '/a+/',      // One or more
            '/a?/',      // Zero or one
            '/a{2}/',    // Exactly 2
            '/a{2,}/',   // 2 or more
            '/a{2,5}/',  // Between 2 and 5
            '/a*?/',     // Lazy zero or more
            '/a+?/',     // Lazy one or more
            '/a??/',     // Lazy zero or one
            '/a{2,5}?/', // Lazy range
            '/a*+/',     // Possessive zero or more
            '/a++/',     // Possessive one or more
            '/a?+/',     // Possessive zero or one
            '/a{2,5}+/', // Possessive range
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $this->assertNotNull($ast);
        }
    }

    /**
     * Test comment.
     */
    public function test_comment(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?#this is a comment)test/');

        $this->assertNotNull($ast);
    }

    /**
     * Test alternation.
     */
    public function test_alternation(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/foo|bar|baz/');

        $this->assertNotNull($ast);
    }

    /**
     * Test empty alternation branches.
     */
    public function test_empty_alternation_branches(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/foo||bar/');

        $this->assertNotNull($ast);
    }

    /**
     * Test complex nested patterns.
     */
    public function test_complex_nested(): void
    {
        $parser = new Parser([]);
        $ast = $parser->parse('/(?:(?<name>test)|(?P<other>foo)){2,5}/');

        $this->assertNotNull($ast);
    }

    /**
     * Test pattern with all delimiter variations.
     */
    public function test_various_delimiters(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/test/',
            '#test#',
            '~test~',
            '@test@',
            '!test!',
            '%test%',
            '{test}',
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $this->assertNotNull($ast);
        }
    }

    /**
     * Test extractPatternAndFlags method with various flags.
     */
    public function test_extract_pattern_and_flags(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/test/i',
            '/test/im',
            '/test/ims',
            '/test/imsx',
            '/test/imsxu',
            '/test/imsxuD',
            '/test/imsxuDU',
            '/test/imsxuDUA',
            '/test/imsxuDUAJ',
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $this->assertNotNull($ast);
        }
    }
}
