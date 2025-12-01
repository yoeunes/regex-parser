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
class FinalCoverageBoostTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_validator_posix_class_variations(): void
    {
        $validator = new ValidatorNodeVisitor();
        $regex = Regex::create();

        // Test various POSIX classes
        $patterns = [
            '/[[:word:]]/',
            '/[[:ascii:]]/',
            '/[[:xdigit:]]/',
        ];

        foreach ($patterns as $pattern) {
            $ast = $regex->parse($pattern);
            $ast->accept($validator);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_validator_unicode_prop_variations(): void
    {
        $validator = new ValidatorNodeVisitor();
        $regex = Regex::create();

        // Test different unicode properties
        $patterns = [
            '/\p{Ll}/',  // Lowercase letter
            '/\p{Lu}/',  // Uppercase letter
            '/\p{N}/',   // Number
            '/\P{L}/',   // Not letter
        ];

        foreach ($patterns as $pattern) {
            $ast = $regex->parse($pattern);
            $ast->accept($validator);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_validator_backref_edge_cases(): void
    {
        $validator = new ValidatorNodeVisitor();
        $regex = Regex::create();

        // Named backref
        $ast = $regex->parse('/(?<name>a)\k<name>/');
        $ast->accept($validator);

        // Numbered backref
        $ast = $regex->parse('/(a)\1/');
        $ast->accept($validator);

        // Relative backref
        $ast = $regex->parse('/(a)\g{-1}/');
        $ast->accept($validator);
    }

    // Test Optimizer edge cases
    public function test_optimizer_char_class_optimization(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $regex = Regex::create();

        // Character class that could be optimized
        $ast = $regex->parse('/[a]/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);

        // Multiple single chars
        $ast = $regex->parse('/[abc]/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_quantifier_edge_cases(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $regex = Regex::create();

        // Quantifier with 0 min
        $ast = $regex->parse('/a{0,5}/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);

        // Quantifier {1,1} should become no quantifier
        $ast = $regex->parse('/a{1,1}/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_sequence_flattening(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $regex = Regex::create();

        // Nested sequences
        $ast = $regex->parse('/abc/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    public function test_optimizer_alternation_with_empty(): void
    {
        $optimizer = new OptimizerNodeVisitor();
        $regex = Regex::create();

        // Alternation with one empty branch
        $ast = $regex->parse('/a|/');
        $result = $ast->accept($optimizer);
        $this->assertNotNull($result);
    }

    // Test Dumper edge cases
    public function test_dumper_group_types(): void
    {
        $dumper = new DumperNodeVisitor();
        $regex = Regex::create();

        // Non-capturing group
        $ast = $regex->parse('/(?:abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);

        // Named group
        $ast = $regex->parse('/(?<name>abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);

        // Atomic group
        $ast = $regex->parse('/(?>abc)/');
        $result = $ast->accept($dumper);
        $this->assertStringContainsString('Group', $result);
    }

    public function test_dumper_assertion_types(): void
    {
        $dumper = new DumperNodeVisitor();
        $regex = Regex::create();

        $patterns = [
            '/(?=abc)/',   // Positive lookahead
            '/(?!abc)/',   // Negative lookahead
            '/(?<=abc)/',  // Positive lookbehind
            '/(?<!abc)/',  // Negative lookbehind
        ];

        foreach ($patterns as $pattern) {
            $ast = $regex->parse($pattern);
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
        $regex = Regex::create();

        // Conditional with lookahead
        $regex->parse('/(?(?=test)yes|no)/');

        // Conditional with lookbehind
        $regex->parse('/(?(?<=test)yes|no)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_subroutine_variations(): void
    {
        $regex = Regex::create();

        // Subroutine by number
        $regex->parse('/(abc)(?1)/');

        // Subroutine by name
        $regex->parse('/(?<name>abc)(?&name)/');

        // Recursive pattern
        $regex->parse('/(?R)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_pcre_verb_with_argument(): void
    {
        $regex = Regex::create();

        // PCRE verb with argument
        $regex->parse('/(*MARK:label)/');

        $regex->parse('/(*PRUNE:name)/');

        $regex->parse('/(*THEN:label)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_complex_char_class(): void
    {
        $regex = Regex::create();

        // Char class with multiple ranges and literals
        $regex->parse('/[a-zA-Z0-9_\-\.]/');

        // Negated char class with POSIX
        $regex->parse('/[^[:digit:]]/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_quantifier_possessive(): void
    {
        $regex = Regex::create();

        // Possessive quantifiers
        $patterns = [
            '/a*+/',
            '/a++/',
            '/a?+/',
            '/a{2,5}+/',
        ];

        foreach ($patterns as $pattern) {
            $regex->parse($pattern);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_parser_unicode_variations(): void
    {
        $regex = Regex::create();

        // Unicode with multiple digits
        $regex->parse('/\u{1F600}/');

        // Octal with braces
        $regex->parse('/\o{177}/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_backref_variations(): void
    {
        $regex = Regex::create();

        // Numbered backref with braces
        $regex->parse('/(a)\g{1}/');

        // Relative backref
        $regex->parse('/(a)(b)\g{-1}/');

        // Named backref with quotes
        $regex->parse('/(?<name>a)\k<name>/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_group_with_modifiers(): void
    {
        $regex = Regex::create();

        // Group with inline modifiers
        $patterns = [
            '/(?i:test)/',     // Case insensitive
            '/(?-i:test)/',    // Negate case insensitive
            '/(?s:test)/',     // Dotall
            '/(?m:test)/',     // Multiline
            '/(?x:test)/',     // Extended
        ];

        foreach ($patterns as $pattern) {
            $regex->parse($pattern);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_parser_anchors_all_types(): void
    {
        $regex = Regex::create();

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
            $regex->parse($pattern);
        }
    }

    public function test_full_integration_complex_pattern(): void
    {
        $regex = Regex::create();

        // A complex real-world-like pattern
        $pattern = '/^(?:(?<scheme>https?):\/\/)?(?<host>[\w\-\.]+)(?::(?<port>\d+))?(?<path>\/[^\s]*)?$/i';

        $regex->parse($pattern);

        $result = $regex->validate($pattern);
        $this->assertTrue($result->isValid);

        $explanation = $regex->explain($pattern);
        $this->assertNotEmpty($explanation);

        $dump = $regex->dump($pattern);
        $this->assertNotEmpty($dump);

        $optimized = $regex->optimize($pattern);
        $this->assertNotEmpty($optimized);
    }

    public function test_sample_generator_edge_cases(): void
    {
        $regex = Regex::create();

        // Alternation
        $sample = $regex->generate('/a|b|c/');
        $this->assertMatchesRegularExpression('/^[abc]$/', $sample);

        // Optional group
        $sample = $regex->generate('/a(bc)?d/');
        $this->assertMatchesRegularExpression('/^a(bc)?d$/', $sample);

        // Nested quantifiers
        $sample = $regex->generate('/(a+)+/');
        $this->assertNotEmpty($sample);
    }

    public function test_validator_range_validation(): void
    {
        $regex = Regex::create();

        // Valid ranges
        $result = $regex->validate('/[a-z]/');
        $this->assertTrue($result->isValid);

        $result = $regex->validate('/[0-9]/');
        $this->assertTrue($result->isValid);

        $result = $regex->validate('/[A-Z]/');
        $this->assertTrue($result->isValid);
    }
}
