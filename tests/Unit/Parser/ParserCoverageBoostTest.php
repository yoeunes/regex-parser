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
use RegexParser\Node\BackrefNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Regex;

/**
 * Tests to improve code coverage for the Parser class.
 * Specifically targeting uncovered parsing branches and edge cases.
 */
final class ParserCoverageBoostTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    /**
     * Test Python-style named groups with single quotes.
     */
    #[DoesNotPerformAssertions]
    public function test_python_named_group_single_quotes(): void
    {
        $this->regexService->parse("/(?P'name'test)/");
    }

    /**
     * Test Python-style named groups with double quotes.
     */
    #[DoesNotPerformAssertions]
    public function test_python_named_group_double_quotes(): void
    {
        $this->regexService->parse('/(?P"name"test)/');
    }

    /**
     * Test Python-style named group with angle brackets.
     */
    #[DoesNotPerformAssertions]
    public function test_python_named_group_angle_brackets(): void
    {
        $this->regexService->parse('/(?P<name>test)/');
    }

    /**
     * Test Python-style subroutine call.
     */
    #[DoesNotPerformAssertions]
    public function test_python_subroutine_call(): void
    {
        $this->regexService->parse('/(?P<name>test)(?P>name)/');
    }

    /**
     * Test Python-style backref.
     */
    public function test_python_backref_not_supported(): void
    {
        $ast = $this->regexService->parse('/(?P<name>test)(?P=name)/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $backref = $ast->pattern->children[1];
        $this->assertInstanceOf(BackrefNode::class, $backref);
        $this->assertSame('\k<name>', $backref->ref);
    }

    /**
     * Test positive lookbehind assertion.
     */
    #[DoesNotPerformAssertions]
    public function test_positive_lookbehind(): void
    {
        $this->regexService->parse('/(?<=test)abc/');
    }

    /**
     * Test negative lookbehind assertion.
     */
    #[DoesNotPerformAssertions]
    public function test_negative_lookbehind(): void
    {
        $this->regexService->parse('/(?<!test)abc/');
    }

    /**
     * Test conditional with number.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_number(): void
    {
        $this->regexService->parse('/(test)(?(1)yes|no)/');
    }

    /**
     * Test conditional with named group reference.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_named_group(): void
    {
        $this->regexService->parse('/(?<name>test)(?(<name>)yes|no)/');
    }

    /**
     * Test conditional with assertion as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookahead_positive(): void
    {
        $this->regexService->parse('/(?((?=test))yes|no)/');
    }

    /**
     * Test conditional with negative lookahead as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookahead_negative(): void
    {
        $this->regexService->parse('/(?((?!test))yes|no)/');
    }

    /**
     * Test conditional with positive lookbehind as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_positive(): void
    {
        $this->regexService->parse('/(?((?<=test))yes|no)/');
    }

    /**
     * Test conditional with negative lookbehind as condition.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_with_lookbehind_negative(): void
    {
        $this->regexService->parse('/(?((?<!test))yes|no)/');
    }

    /**
     * Test conditional without else branch.
     */
    #[DoesNotPerformAssertions]
    public function test_conditional_without_else(): void
    {
        $this->regexService->parse('/(test)(?(1)yes)/');
    }

    /**
     * Test subroutine call with various formats.
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_call_by_number(): void
    {
        $this->regexService->parse('/(test)(?1)/');
    }

    /**
     * Test subroutine call with relative reference.
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_call_relative(): void
    {
        $this->regexService->parse('/(test)(?-1)/');
    }

    /**
     * Test subroutine call with named reference.
     */
    #[DoesNotPerformAssertions]
    public function test_subroutine_call_named(): void
    {
        $this->regexService->parse('/(?<name>test)(?&name)/');
    }

    /**
     * Test atomic group.
     */
    #[DoesNotPerformAssertions]
    public function test_atomic_group(): void
    {
        $this->regexService->parse('/(?>test)/');
    }

    /**
     * Test non-capturing group.
     */
    #[DoesNotPerformAssertions]
    public function test_non_capturing_group(): void
    {
        $this->regexService->parse('/(?:test)/');
    }

    /**
     * Test group with flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_flags_i(): void
    {
        $this->regexService->parse('/(?i:test)/');
    }

    /**
     * Test group with multiple flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_multiple_flags(): void
    {
        $this->regexService->parse('/(?im:test)/');
    }

    /**
     * Test group with negative flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_negative_flags(): void
    {
        $this->regexService->parse('/(?-i:test)/');
    }

    /**
     * Test group with mixed flags.
     */
    #[DoesNotPerformAssertions]
    public function test_group_with_mixed_flags(): void
    {
        $this->regexService->parse('/(?i-m:test)/');
    }

    /**
     * Test character class with range.
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_with_range(): void
    {
        $this->regexService->parse('/[a-z]/');
    }

    /**
     * Test character class with multiple ranges.
     */
    #[DoesNotPerformAssertions]
    public function test_char_class_with_multiple_ranges(): void
    {
        $this->regexService->parse('/[a-zA-Z0-9]/');
    }

    /**
     * Test negated character class.
     */
    #[DoesNotPerformAssertions]
    public function test_negated_char_class(): void
    {
        $this->regexService->parse('/[^a-z]/');
    }

    /**
     * Test POSIX character class.
     */
    #[DoesNotPerformAssertions]
    public function test_posix_char_class(): void
    {
        $this->regexService->parse('/[[:alnum:]]/');
    }

    /**
     * Test negated POSIX character class.
     */
    #[DoesNotPerformAssertions]
    public function test_negated_posix_char_class(): void
    {
        $this->regexService->parse('/[[:^alnum:]]/');
    }

    /**
     * Test octal escape sequences.
     */
    #[DoesNotPerformAssertions]
    public function test_octal_legacy(): void
    {
        $this->regexService->parse('/\101/');
    }

    /**
     * Test modern octal notation.
     */
    #[DoesNotPerformAssertions]
    public function test_octal_modern(): void
    {
        $this->regexService->parse('/\o{101}/');
    }

    /**
     * Test Unicode escape sequences.
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_escape_hex(): void
    {
        $this->regexService->parse('/\x41/');
    }

    /**
     * Test Unicode escape with braces.
     */
    #[DoesNotPerformAssertions]
    public function test_unicode_escape_braces(): void
    {
        $this->regexService->parse('/\u{0041}/');
    }

    /**
     * Test PCRE verbs.
     */
    #[DoesNotPerformAssertions]
    public function test_pcre_verb_fail(): void
    {
        $this->regexService->parse('/(*FAIL)/');
    }

    /**
     * Test PCRE verb with name.
     */
    #[DoesNotPerformAssertions]
    public function test_pcre_verb_mark(): void
    {
        $this->regexService->parse('/(*MARK:test)/');
    }

    /**
     * Test keep \K.
     */
    #[DoesNotPerformAssertions]
    public function test_keep(): void
    {
        $this->regexService->parse('/test\K/');
    }

    /**
     * Test various anchors.
     */
    #[DoesNotPerformAssertions]
    public function test_anchors(): void
    {
        $patterns = [
            '/^test/',    // Start anchor
            '/test$/',    // End anchor
            '/^test$/',   // Both anchors
        ];

        foreach ($patterns as $pattern) {
            $this->regexService->parse($pattern);
        }
    }

    /**
     * Test various assertions.
     */
    #[DoesNotPerformAssertions]
    public function test_assertions(): void
    {
        $patterns = [
            '/\A/',      // Start of string
            '/\z/',      // End of string
            '/\Z/',      // End of string or before newline
            '/\G/',      // Continuing match
            '/\b/',      // Word boundary
            '/\B/',      // Not word boundary
        ];

        foreach ($patterns as $pattern) {
            $this->regexService->parse($pattern);
        }
    }

    /**
     * Test character types.
     */
    #[DoesNotPerformAssertions]
    public function test_char_types(): void
    {
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
            $this->regexService->parse($pattern);
        }
    }

    /**
     * Test backref with number.
     */
    #[DoesNotPerformAssertions]
    public function test_backref_numbered(): void
    {
        $this->regexService->parse('/(test)\1/');
    }

    /**
     * Test backref with k<name>.
     */
    #[DoesNotPerformAssertions]
    public function test_backref_named_k_angle(): void
    {
        $this->regexService->parse('/(?<name>test)\k<name>/');
    }

    /**
     * Test backref with k{name}.
     */
    #[DoesNotPerformAssertions]
    public function test_backref_named_k_brace(): void
    {
        $this->regexService->parse('/(?<name>test)\k{name}/');
    }

    /**
     * Test \g reference with number.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_number(): void
    {
        $this->regexService->parse('/(test)\g1/');
    }

    /**
     * Test \g reference with relative number.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_relative(): void
    {
        $this->regexService->parse('/(test)\g-1/');
    }

    /**
     * Test \g reference with angle brackets.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_angle(): void
    {
        $this->regexService->parse('/(?<name>test)\g<name>/');
    }

    /**
     * Test \g reference with braces.
     */
    #[DoesNotPerformAssertions]
    public function test_g_reference_brace(): void
    {
        $this->regexService->parse('/(?<name>test)\g{name}/');
    }

    /**
     * Test dot (matches any character).
     */
    #[DoesNotPerformAssertions]
    public function test_dot(): void
    {
        $this->regexService->parse('/./');
    }

    /**
     * Test quantifiers.
     */
    #[DoesNotPerformAssertions]
    public function test_quantifiers(): void
    {
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
            $this->regexService->parse($pattern);
        }
    }

    /**
     * Test comment.
     */
    #[DoesNotPerformAssertions]
    public function test_comment(): void
    {
        $this->regexService->parse('/(?#this is a comment)test/');
    }

    /**
     * Test alternation.
     */
    #[DoesNotPerformAssertions]
    public function test_alternation(): void
    {
        $this->regexService->parse('/foo|bar|baz/');
    }

    /**
     * Test empty alternation branches.
     */
    #[DoesNotPerformAssertions]
    public function test_empty_alternation_branches(): void
    {
        $this->regexService->parse('/foo||bar/');
    }

    /**
     * Test complex nested patterns.
     */
    #[DoesNotPerformAssertions]
    public function test_complex_nested(): void
    {
        $this->regexService->parse('/(?:(?<name>test)|(?P<other>foo)){2,5}/');
    }

    /**
     * Test pattern with all delimiter variations.
     */
    #[DoesNotPerformAssertions]
    public function test_various_delimiters(): void
    {
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
            $this->regexService->parse($pattern);
        }
    }

    /**
     * Test extractPatternAndFlags method with various flags.
     */
    #[DoesNotPerformAssertions]
    public function test_extract_pattern_and_flags(): void
    {
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
            $this->regexService->parse($pattern);
        }
    }

    public function test_invalid_group_modifier_syntax(): void
    {
        // Couvre le "Invalid group modifier syntax" à la fin de parseGroupModifier
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid group modifier syntax');
        $this->regexService->parse('/(??)/'); // Syntaxe invalide (??)
    }

    public function test_invalid_syntax_after_p(): void
    {
        // Couvre "Invalid syntax after (?P"
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid syntax after (?P');
        $this->regexService->parse('/(?Px)/'); // P suivi de x n'est pas valide
    }

    public function test_quantifier_without_target(): void
    {
        // Couvre "Quantifier without target" au début de parseQuantifiedAtom
        // (Cas : littéral vide généré par quelque chose d'autre, ou bug interne)
        $this->expectException(ParserException::class);
        $this->regexService->parse('/+/'); // + sans rien avant
    }

    public function test_quantifier_on_anchor(): void
    {
        // Couvre l'interdiction de quantifier une ancre
        $this->expectException(ParserException::class);
        $this->regexService->parse('/^* /');
    }

    public function test_missing_closing_delimiter(): void
    {
        // Couvre "No closing delimiter found"
        $this->expectException(ParserException::class);
        $this->regexService->parse('/abc');
    }

    public function test_unknown_flag(): void
    {
        // Couvre "Unknown regex flag"
        $this->expectException(ParserException::class);
        $this->regexService->parse('/abc/Z');
    }

    public function test_invalid_conditional_condition(): void
    {
        // Couvre le dernier else de parseConditionalCondition
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional condition');
        // (?(?...) où le ? n'est ni = ni ! ni <
        $this->regexService->parse('/(?(?~a)b)/');
    }
}
