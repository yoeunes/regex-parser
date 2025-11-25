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
 * Additional tests targeting specific uncovered branches in Parser.
 */
class AdditionalCoverageBoostTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->compiler = new RegexCompiler([]);
    }

    /**
     * Test unsupported (?P=name) backref syntax - should throw exception
     */
    public function test_unsupported_python_backref_syntax(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreferences (?P=name) are not supported yet');
        $this->parser->parse('/(?<foo>a)(?P=foo)/');
    }

    /**
     * Test invalid syntax after (?P
     */
    public function test_invalid_syntax_after_p(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid syntax after (?P');
        $this->parser->parse('/(?Px)/');
    }

    /**
     * Test subroutine with (?&name) syntax
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_with_ampersand_syntax(): void
    {
        $this->parser->parse('/(?<foo>a)(?&foo)/');
    }

    /**
     * Test numeric subroutines: (?1)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_positive(): void
    {
        $this->parser->parse('/(a)(?1)/');
    }

    /**
     * Test numeric subroutines: (?-1) (relative)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_negative(): void
    {
        $this->parser->parse('/(a)(?-1)/');
    }

    /**
     * Test numeric subroutines: (?0) (whole pattern)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_zero(): void
    {
        $this->parser->parse('/(?0)/');
    }

    /**
     * Test multi-digit numeric subroutine: (?10)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_multi_digit(): void
    {
        $this->parser->parse('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(?10)/');
    }

    /**
     * Test inline flags without colon: (?i)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_without_colon(): void
    {
        $this->parser->parse('/(?i)abc/');
    }

    /**
     * Test inline flags with colon: (?i:abc)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_with_colon(): void
    {
        $this->parser->parse('/(?i:abc)/');
    }

    /**
     * Test inline flags with multiple flags: (?ims)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_multiple(): void
    {
        $this->parser->parse('/(?ims)abc/');
    }

    /**
     * Test inline flags with disable: (?-i)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_disable(): void
    {
        $this->parser->parse('/(?-i)abc/');
    }

    /**
     * Test inline flags mixed: (?i-s)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_mixed(): void
    {
        $this->parser->parse('/(?i-s)abc/');
    }

    /**
     * Test all inline flag options: (?imsxADSUXJ)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_all_options(): void
    {
        $this->parser->parse('/(?imsxADSUXJ)abc/');
    }

    /**
     * Test conditional with lookbehind as condition: (?(?<=...)yes|no)
     * This tests the T_GROUP_MODIFIER_OPEN path in conditionals
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_condition_positive(): void
    {
        $this->parser->parse('/(?(?<=test)yes|no)/');
    }

    /**
     * Test conditional with negative lookbehind as condition: (?(?<!...)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_condition_negative(): void
    {
        $this->parser->parse('/(?(?<!test)yes|no)/');
    }

    /**
     * Test Python-style named group with empty name should fail
     */
    public function test_python_named_group_empty_name(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected group name');
        $this->parser->parse("/(?P'')/");
    }

    /**
     * Test Python-style named group missing closing quote
     */
    public function test_python_named_group_missing_closing_quote(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token in group name');
        $this->parser->parse("/(?P'name)/");
    }

    /**
     * Test parseGroupName missing closing quote (for Python-style names inside parseGroupModifier)
     * Note: Regular (?<name>) doesn't support quotes, only Python (?P'name') does
     */
    public function test_group_name_missing_closing_quote(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unexpected token in group name');
        $this->parser->parse("/(?P'test)/");
    }

    /**
     * Test subroutine with empty name should fail
     */
    public function test_subroutine_empty_name(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected subroutine name');
        $this->parser->parse('/(?P>)/');
    }

    /**
     * Test character class with negation followed by literal ]
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_negation_with_literal_bracket(): void
    {
        $this->parser->parse('/[^]abc]/');
    }

    /**
     * Test character class starting with literal ]
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_starting_with_bracket(): void
    {
        $this->parser->parse('/[]abc]/');
    }

    /**
     * Test alternation at the end: a|
     */
    #[DoesNotPerformAssertions]
    public function test_alternation_empty_right(): void
    {
        $this->parser->parse('/a|/');
    }

    /**
     * Test alternation at the start: |a
     */
    #[DoesNotPerformAssertions]
    public function test_alternation_empty_left(): void
    {
        $this->parser->parse('/|a/');
    }

    /**
     * Test multiple empty alternations: ||
     */
    #[DoesNotPerformAssertions]
    public function test_alternation_multiple_empty(): void
    {
        $this->parser->parse('/||/');
    }

    /**
     * Test comment with special characters
     */
    #[DoesNotPerformAssertions]
    public function test_comment_with_special_chars(): void
    {
        $this->parser->parse('/(?#test.*+?|^$)abc/');
    }

    /**
     * Test nested character classes with POSIX classes
     */
    #[DoesNotPerformAssertions]
    public function test_nested_posix_classes(): void
    {
        $this->parser->parse('/[a[[:digit:]]b]/');
    }

    /**
     * Test character class with range and negation
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_range_with_negation(): void
    {
        $this->parser->parse('/[^a-z]/');
    }

    /**
     * Test backreference with \k<number> syntax
     */
    #[DoesNotPerformAssertions]
    public function test_backref_k_with_number(): void
    {
        $this->parser->parse('/(a)\k<1>/');
    }

    /**
     * Test backreference with \k{number} syntax
     */
    #[DoesNotPerformAssertions]
    public function test_backref_k_with_number_braces(): void
    {
        $this->parser->parse('/(a)\k{1}/');
    }

    /**
     * Test \g with bare number (no braces)
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_bare_number(): void
    {
        $this->parser->parse('/(a)\g1/');
    }

    /**
     * Test quantifier on group
     */
    #[DoesNotPerformAssertions]
    public function test_quantifier_on_group(): void
    {
        $this->parser->parse('/(abc)+/');
    }

    /**
     * Test quantifier on character class
     */
    #[DoesNotPerformAssertions]
    public function test_quantifier_on_char_class(): void
    {
        $this->parser->parse('/[abc]+/');
    }

    /**
     * Test quantifier on assertion (should fail - quantifiers can't be applied to assertions)
     */
    public function test_quantifier_on_assertion(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "+" cannot be applied to assertion');
        $this->parser->parse('/\b+/');
    }

    /**
     * Test empty pattern
     */
    #[DoesNotPerformAssertions]
    public function test_empty_pattern(): void
    {
        $this->parser->parse('//');
    }

    /**
     * Test pattern with only anchors
     */
    #[DoesNotPerformAssertions]
    public function test_pattern_only_anchors(): void
    {
        $this->parser->parse('/^$/');
    }

    /**
     * Test deeply nested groups
     */
    #[DoesNotPerformAssertions]
    public function test_deeply_nested_groups(): void
    {
        $this->parser->parse('/(((((a)))))/');
    }

    /**
     * Test multiple quantifiers in sequence
     */
    #[DoesNotPerformAssertions]
    public function test_multiple_quantifiers(): void
    {
        $this->parser->parse('/a+b*c?d{2,3}/');
    }

    /**
     * Test complex nested alternation
     */
    #[DoesNotPerformAssertions]
    public function test_complex_nested_alternation(): void
    {
        $this->parser->parse('/(a|b)|(c|d)/');
    }

    /**
     * Test character class with multiple ranges
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_multiple_ranges(): void
    {
        $this->parser->parse('/[a-zA-Z0-9_]/');
    }

    /**
     * Test character class with escaped characters
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_with_escapes(): void
    {
        $this->parser->parse('/[\^\-\]]/');
    }

    /**
     * Test unicode property in character class
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_prop_in_char_class(): void
    {
        $this->parser->parse('/[\p{L}\d]/');
    }

    /**
     * Test negated unicode property in character class
     */
    #[DoesNotPerformAssertions]
    public function test_negated_unicode_prop_in_char_class(): void
    {
        $this->parser->parse('/[\P{L}]/');
    }

    /**
     * Test character type in character class
     */
    #[DoesNotPerformAssertions]
    public function test_char_type_in_char_class(): void
    {
        $this->parser->parse('/[\d\s\w]/');
    }

    /**
     * Test octal in character class
     */
    #[DoesNotPerformAssertions]
    public function test_octal_in_char_class(): void
    {
        $this->parser->parse('/[\01\o{77}]/');
    }

    /**
     * Test unicode in character class
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_in_char_class(): void
    {
        $this->parser->parse('/[\x41\u{42}]/');
    }
}
