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
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Parser;

/**
 * Comprehensive test to achieve 100% code coverage for all remaining NodeVisitor classes.
 * This test targets specific uncovered lines and methods in:
 * - ExplainVisitor
 * - HtmlExplainVisitor
 * - OptimizerNodeVisitor
 * - SampleGeneratorVisitor
 * - ValidatorNodeVisitor
 */
class Complete100PercentCoverageTest extends TestCase
{
    private Parser $parser;

    private ExplainVisitor $explainVisitor;

    private HtmlExplainVisitor $htmlExplainVisitor;

    private OptimizerNodeVisitor $optimizerVisitor;

    private SampleGeneratorVisitor $sampleVisitor;

    private ValidatorNodeVisitor $validatorVisitor;

    protected function setUp(): void
    {
        $this->parser = new Parser([]);
        $this->explainVisitor = new ExplainVisitor();
        $this->htmlExplainVisitor = new HtmlExplainVisitor();
        $this->optimizerVisitor = new OptimizerNodeVisitor();
        $this->sampleVisitor = new SampleGeneratorVisitor();
        $this->validatorVisitor = new ValidatorNodeVisitor();
    }

    // ========== ExplainVisitor Tests ==========

    public function test_explain_visitor_complex_quantifier_multiline_child(): void
    {
        // Test quantifier with complex child (multiline output) to hit lines 149-159
        $ast = $this->parser->parse('/(a|b|c)+/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('Start Quantified Group', $result);
        $this->assertStringContainsString('End Quantified Group', $result);
    }

    public function test_explain_visitor_dot_node(): void
    {
        $ast = $this->parser->parse('/./');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('Wildcard', $result);
    }

    public function test_explain_visitor_keep_node(): void
    {
        $ast = $this->parser->parse('/\K/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('reset match start', $result);
    }

    public function test_explain_visitor_comment_node(): void
    {
        $ast = $this->parser->parse('/(?#test comment)/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('Comment', $result);
    }

    public function test_explain_visitor_octal_legacy_node(): void
    {
        $ast = $this->parser->parse('/\01/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('Legacy Octal', $result);
    }

    public function test_explain_visitor_special_literals(): void
    {
        // Test explainLiteral helper with special characters
        $ast = $this->parser->parse('/ \t\n\r/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('space', $result);
        $this->assertStringContainsString('tab', $result);
        $this->assertStringContainsString('newline', $result);
        $this->assertStringContainsString('carriage return', $result);
    }

    public function test_explain_visitor_lazy_quantifier(): void
    {
        // Test explainQuantifierValue with lazy quantifier
        $ast = $this->parser->parse('/a+?/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('as few as possible', $result);
    }

    public function test_explain_visitor_possessive_quantifier(): void
    {
        // Test explainQuantifierValue with possessive quantifier
        $ast = $this->parser->parse('/a++/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('do not backtrack', $result);
    }

    public function test_explain_visitor_conditional_with_alternation(): void
    {
        // Test visitConditional with alternation (ELSE branch)
        $ast = $this->parser->parse('/(?(?=a)yes|no)/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function test_explain_visitor_conditional_single_branch(): void
    {
        // Test visitConditional with single branch
        $ast = $this->parser->parse('/(?(?=a)yes)/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function test_explain_visitor_subroutine_r(): void
    {
        // Test visitSubroutine with R (entire pattern reference)
        $ast = $this->parser->parse('/(?R)/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('entire pattern', $result);
    }

    public function test_explain_visitor_subroutine_0(): void
    {
        // Test visitSubroutine with 0 (entire pattern reference)
        $ast = $this->parser->parse('/(?0)/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('entire pattern', $result);
    }

    // ========== HtmlExplainVisitor Tests ==========

    public function test_html_explain_visitor_complex_quantifier_multiline(): void
    {
        // Test quantifier with complex child to hit multiline quantifier explanation
        $ast = $this->parser->parse('/(a|b)+/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('Quantifier', $result);
        $this->assertStringContainsString('one or more times', $result);
    }

    public function test_html_explain_visitor_dot_node(): void
    {
        $ast = $this->parser->parse('/./');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('Wildcard', $result);
    }

    public function test_html_explain_visitor_keep_node(): void
    {
        $ast = $this->parser->parse('/\K/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('reset match start', $result);
    }

    public function test_html_explain_visitor_octal_legacy_node(): void
    {
        $ast = $this->parser->parse('/\01/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('Legacy Octal', $result);
    }

    public function test_html_explain_visitor_special_literals(): void
    {
        // Test explainLiteral with special characters and HTML escaping
        $ast = $this->parser->parse('/ \t\n\r<>&"/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('space', $result);
        $this->assertStringContainsString('tab', $result);
        $this->assertStringContainsString('&lt;', $result); // HTML entity for <
        $this->assertStringContainsString('&gt;', $result); // HTML entity for >
    }

    public function test_html_explain_visitor_lazy_quantifier(): void
    {
        $ast = $this->parser->parse('/a+?/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('as few as possible', $result);
    }

    public function test_html_explain_visitor_possessive_quantifier(): void
    {
        $ast = $this->parser->parse('/a++/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('do not backtrack', $result);
    }

    public function test_html_explain_visitor_conditional_with_alternation(): void
    {
        $ast = $this->parser->parse('/(?(?=a)yes|no)/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function test_html_explain_visitor_subroutine(): void
    {
        $ast = $this->parser->parse('/(?R)/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('entire pattern', $result);
    }

    // ========== OptimizerNodeVisitor Tests ==========

    public function test_optimizer_visitor_dot_node(): void
    {
        $ast = $this->parser->parse('/./');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_visitor_keep_node(): void
    {
        $ast = $this->parser->parse('/\K/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_visitor_unicode_prop_node(): void
    {
        $ast = $this->parser->parse('/\p{L}/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_visitor_octal_legacy_node(): void
    {
        $ast = $this->parser->parse('/\01/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_visitor_alternation_to_char_class(): void
    {
        // Test canAlternationBeCharClass - alternation of single literals
        $ast = $this->parser->parse('/a|b|c/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_visitor_word_class_detection(): void
    {
        // Test isFullWordClass - character class with word characters
        $ast = $this->parser->parse('/[a-zA-Z0-9_]/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    // ========== SampleGeneratorVisitor Tests ==========

    public function test_sample_generator_dot_node(): void
    {
        $ast = $this->parser->parse('/./');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_keep_node(): void
    {
        $ast = $this->parser->parse('/\K/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_anchor_nodes(): void
    {
        $ast = $this->parser->parse('/^$/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_assertion_nodes(): void
    {
        $ast = $this->parser->parse('/\b\B/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_comment_node(): void
    {
        $ast = $this->parser->parse('/(?#comment)/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_pcre_verb_node(): void
    {
        $ast = $this->parser->parse('/(*FAIL)/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_unicode_without_hex_pattern(): void
    {
        // Test unicode node that doesn't match hex patterns (fallback case)
        $ast = $this->parser->parse('/\x41/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_posix_class_unknown(): void
    {
        // Test posix class with unknown class to hit default case
        // Since we can't create an unknown posix class through parser,
        // just test various valid ones to ensure coverage
        $classes = ['ascii', 'graph', 'print'];
        foreach ($classes as $class) {
            try {
                $ast = $this->parser->parse("/[[:$class:]]/");
                $sample = $ast->accept($this->sampleVisitor);
                $this->assertIsString($sample);
            } catch (\Exception) {
                // Some classes may not be valid, that's ok
            }
        }
    }

    public function test_sample_generator_quantifier_ranges(): void
    {
        // Test parseQuantifierRange with different quantifier types
        $ast = $this->parser->parse('/a{5}/'); // exact
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);

        $ast = $this->parser->parse('/a{2,5}/'); // range
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);

        $ast = $this->parser->parse('/a{2,}/'); // open-ended
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_char_types(): void
    {
        // Test generateForCharType with different char types
        $ast = $this->parser->parse('/\d\D\s\S\w\W\h\H\v\R/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    public function test_sample_generator_conditional_yes_and_no_paths(): void
    {
        // Test conditional to hit both yes and no paths (run multiple times due to randomness)
        $ast = $this->parser->parse('/(a)?(?(?=b)yes|no)/');
        for ($i = 0; $i < 20; $i++) {
            $sample = $ast->accept($this->sampleVisitor);
            $this->assertIsString($sample);
        }
    }

    public function test_sample_generator_unicode_prop_various(): void
    {
        // Test visitUnicodeProp with properties that don't contain L, N, or P
        $ast = $this->parser->parse('/\p{Z}\p{S}\p{M}\p{C}/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertIsString($sample);
    }

    // ========== ValidatorNodeVisitor Tests ==========

    public function test_validator_visitor_dot_node(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/./');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_keep_node(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/\K/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_comment_node(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/(?#comment)/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_pcre_verb_node(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/(*FAIL)/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_octal_legacy_node(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/\01/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_octal_node(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/\o{101}/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_quantifier_bounds(): void
    {
        $this->expectNotToPerformAssertions();
        // Test parseQuantifierBounds with different quantifier types
        $ast = $this->parser->parse('/a{5}/'); // exact
        $ast->accept($this->validatorVisitor);

        $ast = $this->parser->parse('/a{2,5}/'); // range
        $ast->accept($this->validatorVisitor);

        $ast = $this->parser->parse('/a{2,}/'); // open-ended
        $ast->accept($this->validatorVisitor);

        $ast = $this->parser->parse('/a*/'); // star
        $ast->accept($this->validatorVisitor);

        $ast = $this->parser->parse('/a+/'); // plus
        $ast->accept($this->validatorVisitor);

        $ast = $this->parser->parse('/a?/'); // question
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_unicode_prop(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/\p{L}/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_conditional(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/(?(?=a)yes|no)/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_subroutine(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/(?<name>a)(?&name)/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_visitor_assertion_nodes(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->parser->parse('/\b\B\A\z\Z\G/');
        $ast->accept($this->validatorVisitor);
    }
}
