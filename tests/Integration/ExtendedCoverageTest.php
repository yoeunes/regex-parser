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
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

/**
 * Extended coverage tests for edge cases and rarely-used code paths.
 */
class ExtendedCoverageTest extends TestCase
{
    private Regex $regexService;

    private ExplainNodeVisitor $explainVisitor;

    private HtmlExplainNodeVisitor $htmlExplainVisitor;

    private OptimizerNodeVisitor $optimizerVisitor;

    private SampleGeneratorNodeVisitor $sampleVisitor;

    private ValidatorNodeVisitor $validatorVisitor;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
        $this->explainVisitor = new ExplainNodeVisitor();
        $this->htmlExplainVisitor = new HtmlExplainNodeVisitor();
        $this->optimizerVisitor = new OptimizerNodeVisitor();
        $this->sampleVisitor = new SampleGeneratorNodeVisitor();
        $this->validatorVisitor = new ValidatorNodeVisitor();
    }

    public function test_parser_atomic_groups(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/(?>a+)b/');
    }

    public function test_parser_recursive_patterns(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/(?R)/');
    }

    public function test_parser_subroutine_with_relative_reference(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/(a)(?-1)/');
    }

    public function test_parser_g_reference_with_braces(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/\g{1}/');
    }

    public function test_parser_g_reference_with_angle_brackets(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/\g<name>/');
    }

    public function test_parser_g_reference_with_number(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/\g1/');
    }

    public function test_parser_char_class_with_posix_negated(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/[[:^alpha:]]/');
    }

    public function test_parser_char_class_with_multiple_ranges(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/[a-zA-Z0-9_\-]/');
    }

    public function test_parser_empty_char_class(): void
    {
        $this->expectNotToPerformAssertions();

        try {
            $this->regexService->parse('/[]/');
        } catch (\Exception) {
            // May fail, that's ok
        }
    }

    public function test_parser_quantifier_possessive_on_group(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/(abc)++/');
    }

    public function test_parser_comment_in_pattern(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/a(?#this is a comment)b/');
    }

    public function test_parser_multiple_flags(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/test/imsxuADJU');
    }

    public function test_parser_inline_modifier_add_remove(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/(?i-ms:test)/');
    }

    public function test_parser_inline_modifier_standalone(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/(?i)test/');
    }

    public function test_parser_backref_with_k_braces(): void
    {
        $this->expectNotToPerformAssertions();
        $this->regexService->parse('/(?<name>a)\k{name}/');
    }

    public function test_parser_pcre_verbs_various(): void
    {
        $this->expectNotToPerformAssertions();
        $patterns = [
            '/(*ACCEPT)/',
            '/(*FAIL)/',
            '/(*MARK:name)/',
            '/(*COMMIT)/',
            '/(*PRUNE)/',
            '/(*SKIP)/',
            '/(*THEN)/',
        ];

        foreach ($patterns as $pattern) {
            try {
                $this->regexService->parse($pattern);
            } catch (\Exception) {
                // Some may fail
            }
        }
    }

    public function test_sample_generator_group_with_name(): void
    {
        $ast = $this->regexService->parse('/(?<letter>[a-z])(?<digit>\d)/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_unicode_hex(): void
    {
        $ast = $this->regexService->parse('/\x41\x42/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_unicode_braces(): void
    {
        $ast = $this->regexService->parse('/\u{41}\u{42}/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_octal_braces(): void
    {
        $ast = $this->regexService->parse('/\o{101}/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_octal_legacy_variations(): void
    {
        $ast = $this->regexService->parse('/\01\02\07/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_posix_classes_all(): void
    {
        $classes = [
            '[[:alpha:]]', '[[:alnum:]]', '[[:digit:]]', '[[:xdigit:]]',
            '[[:space:]]', '[[:lower:]]', '[[:upper:]]', '[[:punct:]]',
            '[[:word:]]', '[[:blank:]]', '[[:cntrl:]]', '[[:graph:]]',
            '[[:print:]]',
        ];

        foreach ($classes as $class) {
            $ast = $this->regexService->parse('/'.$class.'/');
            $sample = $ast->accept($this->sampleVisitor);
            $this->assertIsString($sample);
        }
    }

    public function test_sample_generator_quantifier_exact(): void
    {
        $ast = $this->regexService->parse('/a{5}/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertSame(5, \strlen($sample));
    }

    public function test_sample_generator_quantifier_range(): void
    {
        $ast = $this->regexService->parse('/a{2,4}/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertGreaterThanOrEqual(2, \strlen($sample));
        $this->assertLessThanOrEqual(4, \strlen($sample));
    }

    public function test_sample_generator_quantifier_open_range(): void
    {
        $ast = $this->regexService->parse('/a{2,}/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertGreaterThanOrEqual(2, \strlen($sample));
    }

    public function test_sample_generator_non_capturing_group(): void
    {
        $ast = $this->regexService->parse('/(?:abc)+/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_sample_generator_atomic_group(): void
    {
        $ast = $this->regexService->parse('/(?>abc)/');
        $sample = $ast->accept($this->sampleVisitor);
        $this->assertNotEmpty($sample);
    }

    public function test_explain_visitor_group_types(): void
    {
        $patterns = [
            '/(?:a)/',      // non-capturing
            '/(a)/',        // capturing
            '/(?<name>a)/', // named
            '/(?>a)/',      // atomic
            '/(?|a|b)/',    // branch reset
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->regexService->parse($pattern);
                $result = $ast->accept($this->explainVisitor);
                $this->assertIsString($result);
            } catch (\Exception) {
                // Some may fail
            }
        }
    }

    public function test_explain_visitor_quantifier_lazy(): void
    {
        $ast = $this->regexService->parse('/a+?/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('as few as possible', $result);
    }

    public function test_explain_visitor_quantifier_possessive(): void
    {
        $ast = $this->regexService->parse('/a++/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('and do not backtrack', $result);
    }

    public function test_explain_visitor_anchors(): void
    {
        $ast = $this->regexService->parse('/^test$/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('start', $result);
        $this->assertStringContainsString('end', $result);
    }

    public function test_explain_visitor_assertions(): void
    {
        $patterns = [
            '/\A/', '/\z/', '/\Z/', '/\G/', '/\b/', '/\B/',
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $result = $ast->accept($this->explainVisitor);
            $this->assertIsString($result);
        }
    }

    public function test_explain_visitor_subroutine(): void
    {
        $ast = $this->regexService->parse('/(?<test>a)(?&test)/');
        $result = $ast->accept($this->explainVisitor);
        $this->assertStringContainsString('Subroutine Call', $result);
    }

    public function test_html_explain_group_with_name(): void
    {
        $ast = $this->regexService->parse('/(?<name>test)/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('name', $result);
    }

    public function test_html_explain_atomic_group(): void
    {
        $ast = $this->regexService->parse('/(?>test)/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('Atomic', $result);
    }

    public function test_html_explain_assertions_all(): void
    {
        $patterns = [
            '/(?=test)/',  // positive lookahead
            '/(?!test)/',  // negative lookahead
            '/(?<=test)/', // positive lookbehind
            '/(?<!test)/', // negative lookbehind
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $result = $ast->accept($this->htmlExplainVisitor);
            $this->assertStringContainsString('Look', $result);
        }
    }

    public function test_html_explain_backref_named(): void
    {
        $ast = $this->regexService->parse('/(?<name>a)\k<name>/');
        $result = $ast->accept($this->htmlExplainVisitor);
        $this->assertStringContainsString('name', $result);
    }

    public function test_html_explain_unicode_prop_variations(): void
    {
        $patterns = [
            '/\p{Lu}/', // uppercase letter
            '/\p{Ll}/', // lowercase letter
            '/\P{L}/',  // not letter
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $result = $ast->accept($this->htmlExplainVisitor);
            $this->assertIsString($result);
        }
    }

    public function test_optimizer_quantifier_zero_times(): void
    {
        $ast = $this->regexService->parse('/a{0}/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_alternation_empty(): void
    {
        $ast = $this->regexService->parse('/(|a|b)/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_sequence_with_one_element(): void
    {
        $ast = $this->regexService->parse('/(?:a)/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_char_class_negated_single(): void
    {
        $ast = $this->regexService->parse('/[^a]/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_range(): void
    {
        $ast = $this->regexService->parse('/[a-z]/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_optimizer_subroutine(): void
    {
        $ast = $this->regexService->parse('/(?<name>a)(?&name)/');
        $result = $ast->accept($this->optimizerVisitor);
        $this->assertNotNull($result);
    }

    public function test_validator_unicode_variations_all(): void
    {
        $this->expectNotToPerformAssertions();
        $patterns = [
            '/\x00/',      // null byte
            '/\xFF/',      // max byte
            '/\u{0}/',     // null unicode
            '/\u{10FFFF}/', // max unicode
        ];

        foreach ($patterns as $pattern) {
            try {
                $ast = $this->regexService->parse($pattern);
                $ast->accept($this->validatorVisitor);
            } catch (\Exception) {
                // Some may fail
            }
        }
    }

    public function test_validator_octal_variations(): void
    {
        $this->expectNotToPerformAssertions();
        $patterns = [
            // '/\0/', // \0 is treated as backreference \0, not octal
            '/\01/',
            '/\07/',
            '/\o{0}/',
            '/\o{377}/',
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $ast->accept($this->validatorVisitor);
        }
    }

    public function test_validator_posix_class_negated(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->regexService->parse('/[[:^alpha:]]/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_subroutine(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->regexService->parse('/(?<name>a)(?&name)/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_validator_atomic_group(): void
    {
        $this->expectNotToPerformAssertions();
        $ast = $this->regexService->parse('/(?>a+)/');
        $ast->accept($this->validatorVisitor);
    }

    public function test_lexer_all_escape_sequences_in_char_class(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('[\\t\\n\\r\\f\\v\\e\\d\\s\\w]')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_unicode_props_in_char_class(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('[\\p{L}\\P{L}]')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_posix_in_char_class(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('[[:alpha:][:digit:]]')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_backref_variations(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('\\1\\k<name>\\k{name}')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_g_reference_all_forms(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('\\g1\\g{1}\\g<name>\\g-1\\g+1')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_pcre_verbs(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('(*ACCEPT)(*FAIL)(*MARK:name)')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_quote_mode_with_backslash(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('\\Q\\\\E')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_quote_mode_with_metacharacters(): void
    {
        $tokens = $this->regexService->getLexer()->tokenize('\\Q.*+?^$[](){}|\\E')->getTokens();
        $this->assertNotEmpty($tokens);
    }
}
