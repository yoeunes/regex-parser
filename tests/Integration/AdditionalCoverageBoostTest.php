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
use RegexParser\Parser;
use RegexParser\Exception\ParserException;

/**
 * Additional tests targeting specific uncovered branches in Parser.
 */
class AdditionalCoverageBoostTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser([]);
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
    public function test_subroutine_with_ampersand_syntax(): void
    {
        $ast = $this->parser->parse('/(?<foo>a)(?&foo)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test numeric subroutines: (?1)
     */
    public function test_numeric_subroutine_positive(): void
    {
        $ast = $this->parser->parse('/(a)(?1)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test numeric subroutines: (?-1) (relative)
     */
    public function test_numeric_subroutine_negative(): void
    {
        $ast = $this->parser->parse('/(a)(?-1)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test numeric subroutines: (?0) (whole pattern)
     */
    public function test_numeric_subroutine_zero(): void
    {
        $ast = $this->parser->parse('/(?0)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test multi-digit numeric subroutine: (?10)
     */
    public function test_numeric_subroutine_multi_digit(): void
    {
        $ast = $this->parser->parse('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(?10)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test inline flags without colon: (?i)
     */
    public function test_inline_flags_without_colon(): void
    {
        $ast = $this->parser->parse('/(?i)abc/');
        $this->assertNotNull($ast);
    }

    /**
     * Test inline flags with colon: (?i:abc)
     */
    public function test_inline_flags_with_colon(): void
    {
        $ast = $this->parser->parse('/(?i:abc)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test inline flags with multiple flags: (?ims)
     */
    public function test_inline_flags_multiple(): void
    {
        $ast = $this->parser->parse('/(?ims)abc/');
        $this->assertNotNull($ast);
    }

    /**
     * Test inline flags with disable: (?-i)
     */
    public function test_inline_flags_disable(): void
    {
        $ast = $this->parser->parse('/(?-i)abc/');
        $this->assertNotNull($ast);
    }

    /**
     * Test inline flags mixed: (?i-s)
     */
    public function test_inline_flags_mixed(): void
    {
        $ast = $this->parser->parse('/(?i-s)abc/');
        $this->assertNotNull($ast);
    }

    /**
     * Test all inline flag options: (?imsxADSUXJ)
     */
    public function test_inline_flags_all_options(): void
    {
        $ast = $this->parser->parse('/(?imsxADSUXJ)abc/');
        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with lookbehind as condition: (?(?<=...)yes|no)
     * This tests the T_GROUP_MODIFIER_OPEN path in conditionals
     */
    public function test_conditional_with_lookbehind_condition_positive(): void
    {
        $ast = $this->parser->parse('/(?(?<=test)yes|no)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test conditional with negative lookbehind as condition: (?(?<!...)yes|no)
     */
    public function test_conditional_with_lookbehind_condition_negative(): void
    {
        $ast = $this->parser->parse('/(?(?<!test)yes|no)/');
        $this->assertNotNull($ast);
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
    public function test_char_class_negation_with_literal_bracket(): void
    {
        $ast = $this->parser->parse('/[^]abc]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test character class starting with literal ]
     */
    public function test_char_class_starting_with_bracket(): void
    {
        $ast = $this->parser->parse('/[]abc]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test alternation at the end: a|
     */
    public function test_alternation_empty_right(): void
    {
        $ast = $this->parser->parse('/a|/');
        $this->assertNotNull($ast);
    }

    /**
     * Test alternation at the start: |a
     */
    public function test_alternation_empty_left(): void
    {
        $ast = $this->parser->parse('/|a/');
        $this->assertNotNull($ast);
    }

    /**
     * Test multiple empty alternations: ||
     */
    public function test_alternation_multiple_empty(): void
    {
        $ast = $this->parser->parse('/||/');
        $this->assertNotNull($ast);
    }

    /**
     * Test comment with special characters
     */
    public function test_comment_with_special_chars(): void
    {
        $ast = $this->parser->parse('/(?#test.*+?|^$)abc/');
        $this->assertNotNull($ast);
    }

    /**
     * Test nested character classes with POSIX classes
     */
    public function test_nested_posix_classes(): void
    {
        $ast = $this->parser->parse('/[a[[:digit:]]b]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test character class with range and negation
     */
    public function test_char_class_range_with_negation(): void
    {
        $ast = $this->parser->parse('/[^a-z]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test backreference with \k<number> syntax
     */
    public function test_backref_k_with_number(): void
    {
        $ast = $this->parser->parse('/(a)\k<1>/');
        $this->assertNotNull($ast);
    }

    /**
     * Test backreference with \k{number} syntax
     */
    public function test_backref_k_with_number_braces(): void
    {
        $ast = $this->parser->parse('/(a)\k{1}/');
        $this->assertNotNull($ast);
    }

    /**
     * Test \g with bare number (no braces)
     */
    public function test_g_reference_bare_number(): void
    {
        $ast = $this->parser->parse('/(a)\g1/');
        $this->assertNotNull($ast);
    }

    /**
     * Test quantifier on group
     */
    public function test_quantifier_on_group(): void
    {
        $ast = $this->parser->parse('/(abc)+/');
        $this->assertNotNull($ast);
    }

    /**
     * Test quantifier on character class
     */
    public function test_quantifier_on_char_class(): void
    {
        $ast = $this->parser->parse('/[abc]+/');
        $this->assertNotNull($ast);
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
    public function test_empty_pattern(): void
    {
        $ast = $this->parser->parse('//');
        $this->assertNotNull($ast);
    }

    /**
     * Test pattern with only anchors
     */
    public function test_pattern_only_anchors(): void
    {
        $ast = $this->parser->parse('/^$/');
        $this->assertNotNull($ast);
    }

    /**
     * Test deeply nested groups
     */
    public function test_deeply_nested_groups(): void
    {
        $ast = $this->parser->parse('/(((((a)))))/');
        $this->assertNotNull($ast);
    }

    /**
     * Test multiple quantifiers in sequence
     */
    public function test_multiple_quantifiers(): void
    {
        $ast = $this->parser->parse('/a+b*c?d{2,3}/');
        $this->assertNotNull($ast);
    }

    /**
     * Test complex nested alternation
     */
    public function test_complex_nested_alternation(): void
    {
        $ast = $this->parser->parse('/(a|b)|(c|d)/');
        $this->assertNotNull($ast);
    }

    /**
     * Test character class with multiple ranges
     */
    public function test_char_class_multiple_ranges(): void
    {
        $ast = $this->parser->parse('/[a-zA-Z0-9_]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test character class with escaped characters
     */
    public function test_char_class_with_escapes(): void
    {
        $ast = $this->parser->parse('/[\^\-\]]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test unicode property in character class
     */
    public function test_unicode_prop_in_char_class(): void
    {
        $ast = $this->parser->parse('/[\p{L}\d]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test negated unicode property in character class
     */
    public function test_negated_unicode_prop_in_char_class(): void
    {
        $ast = $this->parser->parse('/[\P{L}]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test character type in character class
     */
    public function test_char_type_in_char_class(): void
    {
        $ast = $this->parser->parse('/[\d\s\w]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test octal in character class
     */
    public function test_octal_in_char_class(): void
    {
        $ast = $this->parser->parse('/[\01\o{77}]/');
        $this->assertNotNull($ast);
    }

    /**
     * Test unicode in character class
     */
    public function test_unicode_in_char_class(): void
    {
        $ast = $this->parser->parse('/[\x41\u{42}]/');
        $this->assertNotNull($ast);
    }
}
