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
    #[DoesNotPerformAssertions]
    public function test_python_named_group_single_quotes(): void
    {
        $parser = new Parser([]);
        $parser->parse("/(?P'name'test)/");
    }

    /**
     * Test Python-style named groups with double quotes.
     */
    #[DoesNotPerformAssertions]
    public function test_python_named_group_double_quotes(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?P"name"test)/');
    }

    /**
     * Test Python-style named group with angle brackets.
     */
    #[DoesNotPerformAssertions]
    public function test_python_named_group_angle_brackets(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?P<name>test)/');
    }

    /**
     * Test Python-style subroutine call.
     */
    #[DoesNotPerformAssertions]
    public function test_python_subroutine_call(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?P<name>test)(?P>name)/');
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
    #[DoesNotPerformAssertions]
    public function test_positive_lookbehind(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<=test)abc/');
    }

    /**
     * Test negative lookbehind assertion.
     */
    #[DoesNotPerformAssertions]
    public function test_negative_lookbehind(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<!test)abc/');
    }

    /**
     * Test conditional with number.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_number(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(test)(?(1)yes|no)/');
    }

    /**
     * Test conditional with named group reference.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_named_group(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<name>test)(?(<name>)yes|no)/');
    }

    /**
     * Test conditional with assertion as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookahead_positive(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?((?=test))yes|no)/');
    }

    /**
     * Test conditional with negative lookahead as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookahead_negative(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?((?!test))yes|no)/');
    }

    /**
     * Test conditional with positive lookbehind as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_positive(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?((?<=test))yes|no)/');
    }

    /**
     * Test conditional with negative lookbehind as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_negative(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?((?<!test))yes|no)/');
    }

    /**
     * Test conditional without else branch.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_without_else(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(test)(?(1)yes)/');
    }

    /**
     * Test subroutine call with various formats.
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_call_by_number(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(test)(?1)/');
    }

    /**
     * Test subroutine call with relative reference.
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_call_relative(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(test)(?-1)/');
    }

    /**
     * Test subroutine call with named reference.
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_call_named(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<name>test)(?&name)/');
    }

    /**
     * Test atomic group.
     */
    #[DoesNotPerformAssertions]
    public function test_atomic_group(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?>test)/');
    }

    /**
     * Test non-capturing group.
     */
    #[DoesNotPerformAssertions]
    public function test_non_capturing_group(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?:test)/');
    }

    /**
     * Test group with flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_flags_i(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?i:test)/');
    }

    /**
     * Test group with multiple flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_multiple_flags(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?im:test)/');
    }

    /**
     * Test group with negative flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_negative_flags(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?-i:test)/');
    }

    /**
     * Test group with mixed flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_mixed_flags(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?i-m:test)/');
    }

    /**
     * Test character class with range.
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_with_range(): void
    {
        $parser = new Parser([]);
        $parser->parse('/[a-z]/');
    }

    /**
     * Test character class with multiple ranges.
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_with_multiple_ranges(): void
    {
        $parser = new Parser([]);
        $parser->parse('/[a-zA-Z0-9]/');
    }

    /**
     * Test negated character class.
     */
    #[DoesNotPerformAssertions]
    public function test_negated_char_class(): void
    {
        $parser = new Parser([]);
        $parser->parse('/[^a-z]/');
    }

    /**
     * Test POSIX character class.
     */
    #[DoesNotPerformAssertions]
    public function test_posix_char_class(): void
    {
        $parser = new Parser([]);
        $parser->parse('/[[:alnum:]]/');
    }

    /**
     * Test negated POSIX character class.
     */
    #[DoesNotPerformAssertions]
    public function test_negated_posix_char_class(): void
    {
        $parser = new Parser([]);
        $parser->parse('/[[:^alnum:]]/');
    }

    /**
     * Test octal escape sequences.
     */
    #[DoesNotPerformAssertions]
    public function test_octal_legacy(): void
    {
        $parser = new Parser([]);
        $parser->parse('/\101/');
    }

    /**
     * Test modern octal notation.
     */
    #[DoesNotPerformAssertions]
    public function test_octal_modern(): void
    {
        $parser = new Parser([]);
        $parser->parse('/\o{101}/');
    }

    /**
     * Test Unicode escape sequences.
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_escape_hex(): void
    {
        $parser = new Parser([]);
        $parser->parse('/\x41/');
    }

    /**
     * Test Unicode escape with braces.
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_escape_braces(): void
    {
        $parser = new Parser([]);
        $parser->parse('/\u{0041}/');
    }

    /**
     * Test PCRE verbs.
     */
    #[DoesNotPerformAssertions]
    public function test_pcre_verb_fail(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(*FAIL)/');
    }

    /**
     * Test PCRE verb with name.
     */
    #[DoesNotPerformAssertions]
    public function test_pcre_verb_mark(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(*MARK:test)/');
    }

    /**
     * Test keep \K.
     */
    #[DoesNotPerformAssertions]
    public function test_keep(): void
    {
        $parser = new Parser([]);
        $parser->parse('/test\K/');
    }

    /**
     * Test various anchors.
     */
    #[DoesNotPerformAssertions]
    public function test_anchors(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/^test/',    // Start anchor
            '/test$/',    // End anchor
            '/^test$/',   // Both anchors
        ];

        foreach ($patterns as $pattern) {
            $parser->parse($pattern);
        }
    }

    /**
     * Test various assertions.
     */
    #[DoesNotPerformAssertions]
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
            $parser->parse($pattern);
        }
    }

    /**
     * Test character types.
     */
    #[DoesNotPerformAssertions]
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
            $parser->parse($pattern);
        }
    }

    /**
     * Test backref with number.
     */
    #[DoesNotPerformAssertions]
    public function test_backref_numbered(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(test)\1/');
    }

    /**
     * Test backref with k<name>.
     */
    #[DoesNotPerformAssertions]
    public function test_backref_named_k_angle(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<name>test)\k<name>/');
    }

    /**
     * Test backref with k{name}.
     */
    #[DoesNotPerformAssertions]
    public function test_backref_named_k_brace(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<name>test)\k{name}/');
    }

    /**
     * Test \g reference with number.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_number(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(test)\g1/');
    }

    /**
     * Test \g reference with relative number.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_relative(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(test)\g-1/');
    }

    /**
     * Test \g reference with angle brackets.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_angle(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<name>test)\g<name>/');
    }

    /**
     * Test \g reference with braces.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_brace(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?<name>test)\g{name}/');
    }

    /**
     * Test dot (matches any character).
     */
    #[DoesNotPerformAssertions]
    public function test_dot(): void
    {
        $parser = new Parser([]);
        $parser->parse('/./');
    }

    /**
     * Test quantifiers.
     */
    #[DoesNotPerformAssertions]
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
            $parser->parse($pattern);
        }
    }

    /**
     * Test comment.
     */
    #[DoesNotPerformAssertions]
    public function test_comment(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?#this is a comment)test/');
    }

    /**
     * Test alternation.
     */
    #[DoesNotPerformAssertions]
    public function test_alternation(): void
    {
        $parser = new Parser([]);
        $parser->parse('/foo|bar|baz/');
    }

    /**
     * Test empty alternation branches.
     */
    #[DoesNotPerformAssertions]
    public function test_empty_alternation_branches(): void
    {
        $parser = new Parser([]);
        $parser->parse('/foo||bar/');
    }

    /**
     * Test complex nested patterns.
     */
    #[DoesNotPerformAssertions]
    public function test_complex_nested(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?:(?<name>test)|(?P<other>foo)){2,5}/');
    }

    /**
     * Test pattern with all delimiter variations.
     */
    #[DoesNotPerformAssertions]
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
            $parser->parse($pattern);
        }
    }

    /**
     * Test extractPatternAndFlags method with various flags.
     */
    #[DoesNotPerformAssertions]
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
            $parser->parse($pattern);
        }
    }
}
