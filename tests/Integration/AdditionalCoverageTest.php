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
use RegexParser\Builder\RegexBuilder;
use RegexParser\Lexer;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\SampleGeneratorVisitor;
use RegexParser\Parser;

/**
 * Additional tests to reach 100% coverage for various classes.
 */
class AdditionalCoverageTest extends TestCase
{
    // Test Lexer with various edge cases
    public function test_lexer_unicode_prop_normalization(): void
    {
        $lexer = new Lexer('/\p{L}/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);

        // Test negated property
        $lexer = new Lexer('/\P{L}/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);

        // Test double negation
        $lexer = new Lexer('/\P{^L}/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_escaped_literals(): void
    {
        // Test all escaped literals
        $patterns = ['\t', '\n', '\r', '\f', '\v', '\e', '\.', '\[', '\]'];
        foreach ($patterns as $pattern) {
            $lexer = new Lexer('/'.$pattern.'/');
            $tokens = $lexer->tokenize();
            $this->assertNotEmpty($tokens);
        }
    }

    public function test_lexer_quote_mode_without_end(): void
    {
        // Quote mode without \E
        $lexer = new Lexer('/\Qabc/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_quote_mode_with_end(): void
    {
        // Quote mode with \E
        $lexer = new Lexer('/\Qabc\Edef/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_backref_variations(): void
    {
        // Test \g{-1}
        $lexer = new Lexer('/(a)\g{-1}/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);

        // Test \g{1}
        $lexer = new Lexer('/(a)\g{1}/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_octal_legacy(): void
    {
        $lexer = new Lexer('/\01/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_posix_class(): void
    {
        $lexer = new Lexer('/[[:alpha:]]/');
        $tokens = $lexer->tokenize();
        $this->assertNotEmpty($tokens);
    }

    // Test RegexBuilder uncovered methods
    public function test_regex_builder_word_boundary(): void
    {
        $builder = new RegexBuilder();
        $pattern = $builder->wordBoundary()->compile();
        $this->assertStringContainsString('\b', $pattern);
    }

    public function test_regex_builder_with_flags(): void
    {
        $builder = new RegexBuilder();
        $pattern = $builder->literal('test')->withFlags('i')->compile();
        $this->assertStringContainsString('i', $pattern);
    }

    public function test_regex_builder_with_delimiter(): void
    {
        $builder = new RegexBuilder();
        $pattern = $builder->literal('test')->withDelimiter('#')->compile();
        $this->assertStringStartsWith('#', $pattern);
    }

    // Test ExplainVisitor edge cases
    public function test_explain_visitor_range_special_chars(): void
    {
        $parser = new Parser([]);
        $visitor = new ExplainVisitor();

        // Range with special characters
        $ast = $parser->parse('/[0-9]/');
        $result = $ast->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_explain_visitor_quantifier_variations(): void
    {
        $parser = new Parser([]);
        $visitor = new ExplainVisitor();

        // Test different quantifier types
        $patterns = [
            '/a*/',    // Zero or more
            '/a+/',    // One or more
            '/a?/',    // Optional
            '/a{3}/',  // Exactly 3
            '/a{2,}/', // At least 2
            '/a{2,5}/', // Between 2 and 5
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $result = $ast->accept($visitor);
            $this->assertNotEmpty($result);
        }
    }

    public function test_explain_visitor_literal_special_chars(): void
    {
        $parser = new Parser([]);
        $visitor = new ExplainVisitor();

        // Literal with special characters
        $ast = $parser->parse('/\//');
        $result = $ast->accept($visitor);
        $this->assertNotEmpty($result);
    }

    // Test SampleGeneratorVisitor edge cases
    public function test_sample_generator_with_seed(): void
    {
        $generator = new SampleGeneratorVisitor();
        $generator->setSeed(12345);

        $parser = new Parser([]);
        $ast = $parser->parse('/[a-z]/');
        $result = $ast->accept($generator);
        $this->assertNotEmpty($result);
    }

    public function test_sample_generator_reset_seed(): void
    {
        $generator = new SampleGeneratorVisitor();
        $generator->setSeed(12345);
        $generator->resetSeed();

        $parser = new Parser([]);
        $ast = $parser->parse('/[a-z]/');
        $result = $ast->accept($generator);
        $this->assertNotEmpty($result);
    }

    public function test_sample_generator_unicode_prop(): void
    {
        $generator = new SampleGeneratorVisitor();
        $parser = new Parser([]);

        // Test \p{L}
        $ast = $parser->parse('/\p{L}/');
        $result = $ast->accept($generator);
        $this->assertNotEmpty($result);
    }

    public function test_sample_generator_char_type_variations(): void
    {
        $generator = new SampleGeneratorVisitor();
        $parser = new Parser([]);

        $patterns = [
            '/\d/',  // Digit
            '/\D/',  // Non-digit
            '/\w/',  // Word
            '/\W/',  // Non-word
            '/\s/',  // Whitespace
            '/\S/',  // Non-whitespace
            '/\h/',  // Horizontal whitespace
            '/\H/',  // Non-horizontal whitespace
            '/\v/',  // Vertical whitespace
            '/\V/',  // Non-vertical whitespace
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $result = $ast->accept($generator);
        }
    }

    public function test_sample_generator_backref_named(): void
    {
        $generator = new SampleGeneratorVisitor();
        $parser = new Parser([]);

        $ast = $parser->parse('/(?<name>abc)\k<name>/');
        $result = $ast->accept($generator);
        $this->assertStringContainsString('abc', $result);
    }

    public function test_sample_generator_group_non_capturing(): void
    {
        $generator = new SampleGeneratorVisitor();
        $parser = new Parser([]);

        $ast = $parser->parse('/(?:abc)/');
        $result = $ast->accept($generator);
        $this->assertStringContainsString('abc', $result);
    }

    public function test_sample_generator_posix_classes(): void
    {
        $generator = new SampleGeneratorVisitor();
        $parser = new Parser([]);

        // Test various POSIX classes that have sample generation support
        $patterns = [
            '/[[:alnum:]]/',
            '/[[:alpha:]]/',
            '/[[:digit:]]/',
            '/[[:lower:]]/',
            '/[[:upper:]]/',
        ];

        foreach ($patterns as $pattern) {
            $ast = $parser->parse($pattern);
            $result = $ast->accept($generator);
            $this->assertNotEmpty($result);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_parser_group_with_flags(): void
    {
        $parser = new Parser([]);

        // Group with flags
        $parser->parse('/(?i:abc)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_named_group_variations(): void
    {
        $parser = new Parser([]);

        // Named group with angle brackets
        $parser->parse('/(?<name>abc)/');

        // Named group with P syntax
        $parser->parse('/(?P<name>abc)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_assertion_variations(): void
    {
        $parser = new Parser([]);

        $patterns = [
            '/(?=abc)/',   // Positive lookahead
            '/(?!abc)/',   // Negative lookahead
            '/(?<=abc)/',  // Positive lookbehind
            '/(?<!abc)/',  // Negative lookbehind
        ];

        foreach ($patterns as $pattern) {
            $parser->parse($pattern);
        }
    }

    #[DoesNotPerformAssertions]
    public function test_parser_atomic_group(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?>abc)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_recursive_pattern(): void
    {
        $parser = new Parser([]);
        $parser->parse('/(?R)/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_char_class_with_dash(): void
    {
        $parser = new Parser([]);

        // Dash at the beginning
        $parser->parse('/[-abc]/');

        // Dash at the end
        $parser->parse('/[abc-]/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_negated_char_class(): void
    {
        $parser = new Parser([]);
        $parser->parse('/[^abc]/');
    }

    #[DoesNotPerformAssertions]
    public function test_parser_empty_alternation(): void
    {
        $parser = new Parser([]);
        $parser->parse('/abc|/');
    }
}
