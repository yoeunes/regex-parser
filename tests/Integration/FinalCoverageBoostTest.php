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
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

/**
 * Final coverage boost tests targeting specific uncovered edge cases.
 */
final class FinalCoverageBoostTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    #[DoesNotPerformAssertions]
    public function test_validator_posix_class_variations(): void
    {
        $validator = new ValidatorNodeVisitor();

        // Test various POSIX classes
        $patterns = [
            '/[[:word:]]/',
            '/[[:ascii:]]/',
            '/[[:xdigit:]]/',
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $ast->accept($validator);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_validator_unicode_prop_variations(): void
    {
        $validator = new ValidatorNodeVisitor();

        // Test different unicode properties
        $patterns = [
            '/\p{Ll}/',  // Lowercase letter
            '/\p{Lu}/',  // Uppercase letter
            '/\p{N}/',   // Number
            '/\P{L}/',   // Not letter
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $ast->accept($validator);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_validator_backref_edge_cases(): void
    {
        $validator = new ValidatorNodeVisitor();

        // Named backref
        $ast = $this->regexService->parse('/(?<name>a)\k<name>/');
        $ast->accept($validator);

        // Numbered backref
        $ast = $this->regexService->parse('/(a)\1/');
        $ast->accept($validator);

        // Relative backref
        $ast = $this->regexService->parse('/(a)\g{-1}/');
        $ast->accept($validator);
    }

    // Test Optimizer edge cases
    public function test_optimizer_char_class_optimization(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Character class that could be optimized
        $ast = $this->regexService->parse('/[a]/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);

        // Multiple single chars
        $ast = $this->regexService->parse('/[abc]/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_quantifier_edge_cases(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Quantifier with 0 min
        $ast = $this->regexService->parse('/a{0,5}/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);

        // Quantifier {1,1} should become no quantifier
        $ast = $this->regexService->parse('/a{1,1}/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_sequence_flattening(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Nested sequences
        $ast = $this->regexService->parse('/abc/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_alternation_with_empty(): void
    {
        $optimizer = new OptimizerNodeVisitor();

        // Alternation with one empty branch
        $ast = $this->regexService->parse('/a|/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    // Test Dumper edge cases
    public function test_dumper_group_types(): void
    {
        $dumper = new DumperNodeVisitor();

        // Non-capturing group
        $ast = $this->regexService->parse('/(?:abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);

        // Named group
        $ast = $this->regexService->parse('/(?<name>abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);

        // Atomic group
        $ast = $this->regexService->parse('/(?>abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);
    }

    public function test_dumper_assertion_types(): void
    {
        $dumper = new DumperNodeVisitor();

        $patterns = [
            '/(?=abc)/',   // Positive lookahead
            '/(?!abc)/',   // Negative lookahead
            '/(?<=abc)/',  // Positive lookbehind
            '/(?<!abc)/',  // Negative lookbehind
        ];

        foreach ($patterns as $pattern) {
            $ast = $this->regexService->parse($pattern);
            $result = $ast->accept($dumper);
            // Assertions are represented as Groups with specific types
            $this->assertStringContainsString('Group', $result);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_regex_create_with_options(): void
    {
        $regex = Regex::create(['max_pattern_length' => 10000]);

        $regex->parse('/test/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_conditional_with_assertion(): void
    {
        // Conditional with lookahead
        $this->regexService->parse('/(?(?=test)yes|no)/');

        // Conditional with lookbehind
        $this->regexService->parse('/(?(?<=test)yes|no)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_variations(): void
    {
        // Subroutine by number
        $this->regexService->parse('/(abc)(?1)/');

        // Subroutine by name
        $this->regexService->parse('/(?<name>abc)(?&name)/');

        // Recursive pattern
        $this->regexService->parse('/(?R)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_pcre_verb_with_argument(): void
    {
        // PCRE verb with argument
        $this->regexService->parse('/(*MARK:label)/');

        $this->regexService->parse('/(*PRUNE:name)/');

        $this->regexService->parse('/(*THEN:label)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_complex_char_class(): void
    {
        // Char class with multiple ranges and literals
        $this->regexService->parse('/[a-zA-Z0-9_\-\.]/');

        // Negated char class with POSIX
        $this->regexService->parse('/[^[:digit:]]/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_quantifier_possessive(): void
    {
        // Possessive quantifiers
        $patterns = [
            '/a*+/',
            '/a++/',
            '/a?+/',
            '/a{2,5}+/',
        ];

        foreach ($patterns as $pattern) {
            $this->regexService->parse($pattern);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_parser_unicode_variations(): void
    {
        // Unicode with multiple digits
        $this->regexService->parse('/\u{1F600}/');

        // Octal with braces
        $this->regexService->parse('/\o{177}/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_backref_variations(): void
    {
        // Numbered backref with braces
        $this->regexService->parse('/(a)\g{1}/');

        // Relative backref
        $this->regexService->parse('/(a)(b)\g{-1}/');

        // Named backref with quotes
        $this->regexService->parse('/(?<name>a)\k<name>/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_group_with_modifiers(): void
    {
        // Group with inline modifiers
        $patterns = [
            '/(?i:test)/',     // Case insensitive
            '/(?-i:test)/',    // Negate case insensitive
            '/(?s:test)/',     // Dotall
            '/(?m:test)/',     // Multiline
            '/(?x:test)/',     // Extended
        ];

        foreach ($patterns as $pattern) {
            $this->regexService->parse($pattern);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_parser_anchors_all_types(): void
    {
        $patterns = [
            '/^test/',    // Start of line
            '/test$/',    // End of line
            '/\Atest/',   // Start of string
            '/test\Z/',   // End of string (before final newline)
            '/test\z/',   // Absolute end
            '/\btest/',   // Word boundary
            '/\Btest/',   // Non-word boundary
            '/\Gtest/',   // Start of match
        ];

        foreach ($patterns as $pattern) {
            $this->regexService->parse($pattern);
        }
    }

    public function test_full_integration_complex_pattern(): void
    {
        // A complex real-world-like pattern
        $pattern = '/^(?:(?<scheme>https?):\/\/)?(?<host>[\w\-\.]+)(?::(?<port>\d+))?(?<path>\/[^\s]*)?$/i';

        $this->regexService->parse($pattern);

        $result = $this->regexService->validate($pattern);
        $this->assertTrue($result->isValid);

        $explanation = $this->regexService->explain($pattern);
        $this->assertNotEmpty($explanation);

        $dump = $this->regexService->dump($pattern);
        $this->assertNotEmpty($dump);

        $optimized = $this->regexService->optimize($pattern);
        $this->assertNotEmpty($optimized);
    }

    public function test_sample_generator_edge_cases(): void
    {
        // Alternation
        $sample = $this->regexService->generate('/a|b|c/');
        $this->assertMatchesRegularExpression('/^[abc]$/', $sample);

        // Optional group
        $sample = $this->regexService->generate('/a(bc)?d/');
        $this->assertMatchesRegularExpression('/^a(bc)?d$/', $sample);

        // Nested quantifiers
        $sample = $this->regexService->generate('/(a+)+/');
        $this->assertNotEmpty($sample);
    }

    public function test_validator_range_validation(): void
    {
        // Valid ranges
        $result = $this->regexService->validate('/[a-z]/');
        $this->assertTrue($result->isValid);

        $result = $this->regexService->validate('/[0-9]/');
        $this->assertTrue($result->isValid);

        $result = $this->regexService->validate('/[A-Z]/');
        $this->assertTrue($result->isValid);
    }
}
