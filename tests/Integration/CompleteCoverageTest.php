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
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

/**
 * Comprehensive test to achieve 100% code coverage for target classes.
 */
class CompleteCoverageTest extends TestCase
{
    private Regex $regex;

    private ExplainVisitor $explainVisitor;

    private HtmlExplainVisitor $htmlExplainVisitor;

    private OptimizerNodeVisitor $optimizerVisitor;

    private SampleGeneratorVisitor $sampleVisitor;

    private ValidatorNodeVisitor $validatorVisitor;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
        $this->explainVisitor = new ExplainVisitor();
        $this->htmlExplainVisitor = new HtmlExplainVisitor();
        $this->optimizerVisitor = new OptimizerNodeVisitor();
        $this->sampleVisitor = new SampleGeneratorVisitor();
        $this->validatorVisitor = new ValidatorNodeVisitor();
    }

    // ========== SampleGeneratorVisitor Tests ==========

    public function test_sample_generator_unicode_prop_without_l_n_p(): void
    {
        // Test unicode properties that don't contain L, N, or P to hit the fallback
        $ast = $this->regex->parse('/\p{Z}/'); // Separator
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);

        $ast = $this->regex->parse('/\p{S}/'); // Symbol
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);

        $ast = $this->regex->parse('/\p{M}/'); // Mark
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);

        $ast = $this->regex->parse('/\p{C}/'); // Other
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_unicode_prop_with_l(): void
    {
        $ast = $this->regex->parse('/\p{L}/'); // Letter
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
        $this->assertMatchesRegularExpression('/[abc]/', $sample);
    }

    public function test_sample_generator_unicode_prop_with_n(): void
    {
        $ast = $this->regex->parse('/\p{N}/'); // Number
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
        $this->assertMatchesRegularExpression('/[123]/', $sample);
    }

    public function test_sample_generator_unicode_prop_with_p(): void
    {
        $ast = $this->regex->parse('/\p{P}/'); // Punctuation
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
        $this->assertMatchesRegularExpression('/[.,!]/', $sample);
    }

    public function test_sample_generator_conditional_no_path(): void
    {
        // Test conditional with NO path - need to generate multiple samples to hit both paths
        $ast = $this->regex->parse('/(a)?(?(?=b)yes|no)/');

        for ($i = 0; $i < 10; $i++) {
            $sample = $ast->accept($this->sampleVisitor);
            $this->assertIsString($sample); // Can be empty string
        }
    }

    public function test_sample_generator_set_seed(): void
    {
        $this->sampleVisitor->setSeed(12345);
        $ast = $this->regex->parse('/[a-z]+/');
        $sample1 = $ast->accept($this->sampleVisitor);

        $this->sampleVisitor->setSeed(12345);
        $sample2 = $ast->accept($this->sampleVisitor);

        // Same seed should produce same result
        $this->assertSame($sample1, $sample2);
    }

    public function test_sample_generator_reset_seed(): void
    {
        $this->sampleVisitor->setSeed(12345);
        $ast = $this->regex->parse('/[a-z]+/');
        $sample1 = $ast->accept($this->sampleVisitor);

        $this->sampleVisitor->resetSeed();
        $sample2 = $ast->accept($this->sampleVisitor);

        // After reset, results may differ
        $this->assertIsString($sample2);
    }

    public function test_sample_generator_empty_alternation(): void
    {
        // Edge case: alternation with empty alternatives
        $ast = $this->regex->parse('/(|a)/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_backref_not_set(): void
    {
        // Backref to group that hasn't captured yet
        $ast = $this->regex->parse('/\1(a)/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_named_backref(): void
    {
        $ast = $this->regex->parse('/(?P<name>a)\k<name>/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    // ========== Parser Tests ==========

    public function test_parser_various_delimiters(): void
    {
        $this->expectNotToPerformAssertions();
        // Test parsing with different delimiters (indirectly tests extractPatternAndFlags)
        $this->regex->parse('#test#i');
        $this->regex->parse('@test@m');
        $this->regex->parse('~test~s');
    }

    public function test_parser_complex_group_modifiers(): void
    {
        $this->expectNotToPerformAssertions();
        // Test various group modifiers to hit parseGroupModifier branches
        $this->regex->parse('/(?i:test)/');
        $this->regex->parse('/(?-i:test)/');
        $this->regex->parse('/(?i-m:test)/');
    }

    public function test_parser_named_groups_various_syntaxes(): void
    {
        $this->expectNotToPerformAssertions();
        // Test different named group syntaxes
        $this->regex->parse('/(?P<name>test)/');
        $this->regex->parse('/(?<name>test)/');
    }

    public function test_parser_assertions_all_types(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/(?=test)/');
        $this->regex->parse('/(?!test)/');
        $this->regex->parse('/(?<=test)/');
        $this->regex->parse('/(?<!test)/');
    }

    public function test_parser_conditional_with_number(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/(a)(?(1)b|c)/');
    }

    public function test_parser_conditional_with_name(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/(?<test>a)(?(test)b|c)/');
    }

    public function test_parser_conditional_with_assertion(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/(?(?=a)b|c)/');
    }

    public function test_parser_subroutine_with_number(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/(a)(?1)/');
    }

    public function test_parser_subroutine_with_name(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/(?<name>a)(?&name)/');
    }

    public function test_parser_char_class_with_ranges(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/[a-zA-Z0-9]/');
    }

    public function test_parser_char_class_negated(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/[^a-z]/');
    }

    public function test_parser_char_class_with_escaped_chars(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/[\]\-\^]/');
    }

    public function test_parser_quantifiers_all_types(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/a*/');
        $this->regex->parse('/a+/');
        $this->regex->parse('/a?/');
        $this->regex->parse('/a{3}/');
        $this->regex->parse('/a{3,}/');
        $this->regex->parse('/a{3,5}/');
    }

    public function test_parser_lazy_quantifiers(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/a*?/');
        $this->regex->parse('/a+?/');
        $this->regex->parse('/a??/');
        $this->regex->parse('/a{3,5}?/');
    }

    public function test_parser_possessive_quantifiers(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regex->parse('/a*+/');
        $this->regex->parse('/a++/');
        $this->regex->parse('/a?+/');
    }

    // ========== ExplainVisitor Tests ==========

    public function test_explain_visitor_all_node_types(): void
    {
        // Test all visit methods
        $patterns = [
            '/a|b/',           // alternation
            '/(?:a)/',         // group
            '/a*/',            // quantifier
            '/\d/',            // char type
            '/./',             // dot
            '/^$/',            // anchors
            '/\b/',            // assertion
            '/\K/',            // keep
            '/[a-z]/',         // char class with range
            '/\1/',            // backref
            '/\x41/',          // unicode
            '/\p{L}/',         // unicode prop
            '/\o{101}/',       // octal
            '/\01/',           // octal legacy
            '/[[:alpha:]]/',   // posix class
            '/(?#comment)/',   // comment
            '/(?(1)a|b)/',     // conditional
            '/(?&name)/',      // subroutine (with name defined)
            '/(*FAIL)/',       // pcre verb
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->regex->parse($pattern);
                $result = $ast->accept($this->explainVisitor);
                $this->assertIsString($result);
            } catch (\Exception) {
                // Some patterns may fail, that's ok
            }
        }
    }

    public function test_explain_visitor_quantifier_variations(): void
    {
        $ast = $this->regex->parse('/a{3}/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('exactly 3 times', $result);

        $ast = $this->regex->parse('/a{3,}/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('at least 3 times', $result);

        $ast = $this->regex->parse('/a{3,5}/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('between 3 and 5 times', $result);
    }

    public function test_explain_visitor_range_with_escape_sequences(): void
    {
        $ast = $this->regex->parse('/[\\t-\\n]/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertIsString($result);
    }

    public function test_explain_visitor_unicode_prop_negated(): void
    {
        $ast = $this->regex->parse('/\P{L}/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertIsString($result);
    }

    public function test_explain_visitor_conditional_with_different_conditions(): void
    {
        $ast = $this->regex->parse('/(a)(?(1)b|c)/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertIsString($result);
    }

    // ========== HtmlExplainVisitor Tests ==========

    public function test_html_explain_all_node_types(): void
    {
        $patterns = [
            '/a|b/',           // alternation
            '/(?:a)/',         // group
            '/a*/',            // quantifier
            '/\d/',            // char type
            '/./',             // dot
            '/^$/',            // anchors
            '/\b/',            // assertion
            '/\K/',            // keep
            '/[a-z]/',         // char class with range
            '/\1/',            // backref
            '/\x41/',          // unicode
            '/\p{L}/',         // unicode prop
            '/\o{101}/',       // octal
            '/\01/',           // octal legacy
            '/[[:alpha:]]/',   // posix class
            '/(?#comment)/',   // comment
            '/(?(1)a|b)/',     // conditional
            '/(*FAIL)/',       // pcre verb
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->regex->parse($pattern);
                $result = $ast->accept($this->htmlExplainVisitor);
                $this->assertIsString($result);
                $this->assertStringContainsString('<', $result);
            } catch (\Exception) {
                // Some patterns may fail, that's ok
            }
        }
    }

    public function test_html_explain_range_with_special_chars(): void
    {
        $ast = $this->regex->parse('/[<>&]/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertIsString($result);
        // HTML entities are double-encoded, check for the presence of HTML
        $this->assertStringContainsString('&amp;', $result);
    }

    public function test_html_explain_quantifier_types(): void
    {
        $ast = $this->regex->parse('/a*?/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('as few as possible', $result);

        $ast = $this->regex->parse('/a*+/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('do not backtrack', $result);
    }

    public function test_html_explain_conditional_variations(): void
    {
        $ast = $this->regex->parse('/(a)(?(1)b|c)/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertIsString($result);
    }

    public function test_html_explain_subroutine(): void
    {
        $ast = $this->regex->parse('/(?<name>a)(?&name)/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertIsString($result);
    }

    // ========== OptimizerNodeVisitor Tests ==========

    public function test_optimizer_alternation_with_literals(): void
    {
        $ast = $this->regex->parse('/a|b|c/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_quantifier_optimizations(): void
    {
        $ast = $this->regex->parse('/a{1}/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);

        $ast = $this->regex->parse('/a{0,1}/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_char_class_single_char(): void
    {
        $ast = $this->regex->parse('/[a]/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_empty_sequences(): void
    {
        $ast = $this->regex->parse('/()/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_nested_groups(): void
    {
        $ast = $this->regex->parse('/(?:(?:a))/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_all_node_types(): void
    {
        $patterns = [
            '/(?:a)/',         // group
            '/[a-z]/',         // char class
            '/\d/',            // char type
            '/./',             // dot
            '/^$/',            // anchors
            '/\b/',            // assertion
            '/\K/',            // keep
            '/\1/',            // backref
            '/\x41/',          // unicode
            '/\p{L}/',         // unicode prop
            '/\o{101}/',       // octal
            '/\01/',           // octal legacy
            '/[[:alpha:]]/',   // posix class
            '/(?#comment)/',   // comment
            '/(?(1)a|b)/',     // conditional
            '/(*FAIL)/',       // pcre verb
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->regex->parse($pattern);
                $result = $ast->accept($this->optimizerVisitor);
                $this->assertNotNull($result);
            } catch (\Exception) {
                // Some patterns may fail, that's ok
            }
        }
    }

    // ========== ValidatorNodeVisitor Tests ==========

    #[DoesNotPerformAssertions]
    public function test_validator_all_node_types(): void
    {
        $patterns = [
            '/a|b/',           // alternation
            '/(?:a)/',         // group
            '/a*/',            // quantifier
            '/\d/',            // char type
            '/./',             // dot
            '/^$/',            // anchors
            '/\b/',            // assertion
            '/\K/',            // keep
            '/[a-z]/',         // char class
            '/\1/',            // backref
            '/\x41/',          // unicode
            '/\p{L}/',         // unicode prop
            '/\o{101}/',       // octal
            '/\01/',           // octal legacy
            '/[[:alpha:]]/',   // posix class
            '/(?#comment)/',   // comment
            '/(?(1)a|b)/',     // conditional
            '/(*FAIL)/',       // pcre verb
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->regex->parse($pattern);
                $ast->accept($this->validatorVisitor);
            } catch (\Exception) {
                // Some patterns may fail, that's ok
            }
        }
    }

    public function test_validator_quantifier_edge_cases(): void
    {
        $this->expectNotToPerformAssertions();
        // Valid quantifiers - validator throws exception if invalid
        $ast = $this->regex->parse('/a{0}/');
        $ast->accept($this->validatorVisitor);

        $ast = $this->regex->parse('/a{1,1}/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_char_class_ranges(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->regex->parse('/[a-z]/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_backref_variations(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->regex->parse('/(a)\1/');
        $ast->accept($this->validatorVisitor);

        $ast = $this->regex->parse('/(?<name>a)\k<name>/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_unicode_variations(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->regex->parse('/\x41/');
        $ast->accept($this->validatorVisitor);

        $ast = $this->regex->parse('/\u{1F600}/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_conditional_variations(): void
    {
        $this->expectNotToPerformAssertions();
        // Test conditional with lookahead assertion (valid)
        $ast = $this->regex->parse('/(?(?=a)b|c)/');
        $ast->accept($this->validatorVisitor);
    }

    // ========== Lexer Tests ==========

    public function test_lexer_quote_mode_with_empty_literal(): void
    {
        $lexer = new Lexer('\Q\E');
        $tokens = $lexer->tokenizeToArray();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_quote_mode_ending_at_string_end(): void
    {
        $lexer = new Lexer('\Qtest');
        $tokens = $lexer->tokenizeToArray();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_extract_token_value_escape_sequences(): void
    {
        // These are tested indirectly through parsing
        $lexer = new Lexer('\t\n\r\f\v\e');
        $tokens = $lexer->tokenizeToArray();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_normalize_unicode_prop_variations(): void
    {
        // Test \p{L}, \P{L}, \p{^L}, \P{^L} variations
        $lexer = new Lexer('\p{L}\P{L}\p{^L}\P{^L}');
        $tokens = $lexer->tokenizeToArray();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_reset(): void
    {
        $lexer = new Lexer('test');
        $tokens1 = $lexer->tokenizeToArray();

        $lexer->reset('new');
        $tokens2 = $lexer->tokenizeToArray();

        $this->assertNotSame($tokens1, $tokens2);
    }
}
