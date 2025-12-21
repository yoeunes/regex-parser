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
use RegexParser\Node\RegexNode;
use RegexParser\Regex;

/**
 * Additional tests targeting specific uncovered branches in Parser.
 */
final class AdditionalCoverageBoostTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    /**
     * Test supported (?P=name) backref syntax
     */
    public function test_unsupported_python_backref_syntax(): void
    {
        $ast = $this->parseRegex('/(?<foo>a)(?P=foo)/');
        $this->assertInstanceOf(\RegexParser\Node\SequenceNode::class, $ast->pattern);
        $backref = $ast->pattern->children[1];
        $this->assertInstanceOf(\RegexParser\Node\BackrefNode::class, $backref);
        $this->assertSame('\k<foo>', $backref->ref);
    }

    /**
     * Test invalid syntax after (?P
     */
    public function test_invalid_syntax_after_p(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid syntax after (?P');
        $this->parseRegex('/(?Px)/');
    }

    /**
     * Test subroutine with (?&name) syntax
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_with_ampersand_syntax(): void
    {
        $this->parseRegex('/(?<foo>a)(?&foo)/');
    }

    /**
     * Test numeric subroutines: (?1)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_positive(): void
    {
        $this->parseRegex('/(a)(?1)/');
    }

    /**
     * Test numeric subroutines: (?-1) (relative)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_negative(): void
    {
        $this->parseRegex('/(a)(?-1)/');
    }

    /**
     * Test numeric subroutines: (?0) (whole pattern)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_zero(): void
    {
        $this->parseRegex('/(?0)/');
    }

    /**
     * Test multi-digit numeric subroutine: (?10)
     */
    #[DoesNotPerformAssertions]
    public function test_numeric_subroutine_multi_digit(): void
    {
        $this->parseRegex('/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)(?10)/');
    }

    /**
     * Test inline flags without colon: (?i)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_without_colon(): void
    {
        $this->parseRegex('/(?i)abc/');
    }

    /**
     * Test inline flags with colon: (?i:abc)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_with_colon(): void
    {
        $this->parseRegex('/(?i:abc)/');
    }

    /**
     * Test inline flags with multiple flags: (?ims)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_multiple(): void
    {
        $this->parseRegex('/(?ims)abc/');
    }

    /**
     * Test inline flags with disable: (?-i)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_disable(): void
    {
        $this->parseRegex('/(?-i)abc/');
    }

    /**
     * Test inline flags mixed: (?i-s)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_mixed(): void
    {
        $this->parseRegex('/(?i-s)abc/');
    }

    /**
     * Test all inline flag options: (?imsxADSUXJ)
     */
    #[DoesNotPerformAssertions]
    public function test_inline_flags_all_options(): void
    {
        $this->parseRegex('/(?imsxADSUXJ)abc/');
    }

    /**
     * Test conditional with lookbehind as condition: (?(?<=...)yes|no)
     * This tests the T_GROUP_MODIFIER_OPEN path in conditionals
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_condition_positive(): void
    {
        $this->parseRegex('/(?(?<=test)yes|no)/');
    }

    /**
     * Test conditional with negative lookbehind as condition: (?(?<!...)yes|no)
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_condition_negative(): void
    {
        $this->parseRegex('/(?(?<!test)yes|no)/');
    }

    /**
     * Test Python-style named group with empty name should fail
     */
    public function test_python_named_group_empty_name(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected group name');
        $this->regexService->parse("/(?P'')/");
    }

    /**
     * Test Python-style named group missing closing quote
     */
    public function test_python_named_group_missing_closing_quote(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected closing quote');
        $this->regexService->parse("/(?P'name)/");
    }

    /**
     * Test parseGroupName missing closing quote (for Python-style names inside parseGroupModifier)
     * Note: Regular (?<name>) doesn't support quotes, only Python (?P'name') does
     */
    public function test_group_name_missing_closing_quote(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected > after group name');
        $this->regexService->parse('/(?<name)/');
    }

    /**
     * Test subroutine with empty name should fail
     */
    public function test_subroutine_empty_name(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected subroutine name');
        $this->regexService->parse('/(?P>)/');
    }

    /**
     * Test character class with negation followed by literal ]
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_negation_with_literal_bracket(): void
    {
        $this->parseRegex('/[^]abc]/');
    }

    /**
     * Test character class starting with literal ]
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_starting_with_bracket(): void
    {
        $this->parseRegex('/[]abc]/');
    }

    /**
     * Test alternation at the end: a|
     */
    #[DoesNotPerformAssertions]
    public function test_alternation_empty_right(): void
    {
        $this->parseRegex('/a|/');
    }

    /**
     * Test alternation at the start: |a
     */
    #[DoesNotPerformAssertions]
    public function test_alternation_empty_left(): void
    {
        $this->parseRegex('/|a/');
    }

    /**
     * Test multiple empty alternations: ||
     */
    #[DoesNotPerformAssertions]
    public function test_alternation_multiple_empty(): void
    {
        $this->parseRegex('/||/');
    }

    /**
     * Test comment with special characters
     */
    #[DoesNotPerformAssertions]
    public function test_comment_with_special_chars(): void
    {
        $this->parseRegex('/(?#test.*+?|^$)abc/');
    }

    /**
     * Test nested character classes with POSIX classes
     */
    #[DoesNotPerformAssertions]
    public function test_nested_posix_classes(): void
    {
        $this->parseRegex('/[a[[:digit:]]b]/');
    }

    /**
     * Test character class with range and negation
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_range_with_negation(): void
    {
        $this->parseRegex('/[^a-z]/');
    }

    /**
     * Test backreference with \k<number> syntax
     */
    #[DoesNotPerformAssertions]
    public function test_backref_k_with_number(): void
    {
        $this->parseRegex('/(a)\k<1>/');
    }

    /**
     * Test backreference with \k{number} syntax
     */
    #[DoesNotPerformAssertions]
    public function test_backref_k_with_number_braces(): void
    {
        $this->parseRegex('/(a)\k{1}/');
    }

    /**
     * Test \g with bare number (no braces)
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_bare_number(): void
    {
        $this->parseRegex('/(a)\g1/');
    }

    /**
     * Test quantifier on group
     */
    #[DoesNotPerformAssertions]
    public function test_quantifier_on_group(): void
    {
        $this->parseRegex('/(abc)+/');
    }

    /**
     * Test quantifier on character class
     */
    #[DoesNotPerformAssertions]
    public function test_quantifier_on_char_class(): void
    {
        $this->parseRegex('/[abc]+/');
    }

    /**
     * Test quantifier on assertion (should fail - quantifiers can't be applied to assertions)
     */
    public function test_quantifier_on_assertion(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Quantifier "+" cannot be applied to assertion');
        $this->regexService->parse('/\b+/');
    }

    /**
     * Test empty pattern
     */
    #[DoesNotPerformAssertions]
    public function test_empty_pattern(): void
    {
        $this->parseRegex('//');
    }

    /**
     * Test pattern with only anchors
     */
    #[DoesNotPerformAssertions]
    public function test_pattern_only_anchors(): void
    {
        $this->parseRegex('/^$/');
    }

    /**
     * Test deeply nested groups
     */
    #[DoesNotPerformAssertions]
    public function test_deeply_nested_groups(): void
    {
        $this->parseRegex('/(((((a)))))/');
    }

    /**
     * Test multiple quantifiers in sequence
     */
    #[DoesNotPerformAssertions]
    public function test_multiple_quantifiers(): void
    {
        $this->parseRegex('/a+b*c?d{2,3}/');
    }

    /**
     * Test complex nested alternation
     */
    #[DoesNotPerformAssertions]
    public function test_complex_nested_alternation(): void
    {
        $this->parseRegex('/(a|b)|(c|d)/');
    }

    /**
     * Test character class with multiple ranges
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_multiple_ranges(): void
    {
        $this->parseRegex('/[a-zA-Z0-9_]/');
    }

    /**
     * Test character class with escaped characters
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_with_escapes(): void
    {
        $this->parseRegex('/[\^\-\]]/');
    }

    /**
     * Test unicode property in character class
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_prop_in_char_class(): void
    {
        $this->parseRegex('/[\p{L}\d]/');
    }

    /**
     * Test negated unicode property in character class
     */
    #[DoesNotPerformAssertions]
    public function test_negated_unicode_prop_in_char_class(): void
    {
        $this->parseRegex('/[\P{L}]/');
    }

    /**
     * Test character type in character class
     */
    #[DoesNotPerformAssertions]
    public function test_char_type_in_char_class(): void
    {
        $this->parseRegex('/[\d\s\w]/');
    }

    /**
     * Test octal in character class
     */
    #[DoesNotPerformAssertions]
    public function test_octal_in_char_class(): void
    {
        $this->parseRegex('/[\01\o{77}]/');
    }

    /**
     * Test unicode in character class
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_in_char_class(): void
    {
        $this->parseRegex('/[\x41\u{42}]/');
    }

    /**
     * Helper method to parse a regex string.
     */
    private function parseRegex(string $pattern): RegexNode
    {
        return $this->regexService->parse($pattern);
    }
}
