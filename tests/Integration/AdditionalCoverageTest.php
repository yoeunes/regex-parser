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
use RegexParser\Lexer;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\Regex;

/**
 * Additional tests to reach 100% coverage for various classes.
 */
final class AdditionalCoverageTest extends TestCase
{
    // Test Lexer with various edge cases
    public function test_lexer_unicode_prop_normalization(): void
    {
        $tokens = (new Lexer())->tokenize('/\p{L}/')->getTokens();
        $this->assertNotEmpty($tokens);

        // Test negated property
        $tokens = (new Lexer())->tokenize('/\P{L}/')->getTokens();
        $this->assertNotEmpty($tokens);

        // Test double negation
        $tokens = (new Lexer())->tokenize('/\P{^L}/')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_escaped_literals(): void
    {
        // Test all escaped literals
        $patterns = ['\t', '\n', '\r', '\f', '\v', '\e', '\.', '\[', '\]'];
        foreach ($patterns as $pattern) {
            $tokens = (new Lexer())->tokenize('/'.$pattern.'/')->getTokens();
            $this->assertNotEmpty($tokens);
        }
    }

    public function test_lexer_quote_mode_without_end(): void
    {
        // Quote mode without \E
        $tokens = (new Lexer())->tokenize('/\Qabc/')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_quote_mode_with_end(): void
    {
        // Quote mode with \E
        $tokens = (new Lexer())->tokenize('/\Qabc\Edef/')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_backref_variations(): void
    {
        // Test \g{-1}
        $tokens = (new Lexer())->tokenize('/(a)\g{-1}/')->getTokens();
        $this->assertNotEmpty($tokens);

        // Test \g{1}
        $tokens = (new Lexer())->tokenize('/(a)\g{1}/')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_octal_legacy(): void
    {
        $tokens = (new Lexer())->tokenize('/\01/')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    public function test_lexer_posix_class(): void
    {
        $tokens = (new Lexer())->tokenize('/[[:alpha:]]/')->getTokens();
        $this->assertNotEmpty($tokens);
    }

    // Test ExplainVisitor edge cases
    public function test_explain_visitor_range_special_chars(): void
    {
        $regex = Regex::create();
        $visitor = new ExplainNodeVisitor();

        // Range with special characters
        $ast = $regex->parse('/[0-9]/');
        $result = $ast->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_explain_visitor_quantifier_variations(): void
    {
        $regex = Regex::create();
        $visitor = new ExplainNodeVisitor();

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
            $ast = $regex->parse($pattern);
            $result = $ast->accept($visitor);
            $this->assertNotEmpty($result);
        }
    }

    public function test_explain_visitor_literal_special_chars(): void
    {
        $regex = Regex::create();
        $visitor = new ExplainNodeVisitor();

        // Literal with special characters
        $ast = $regex->parse('/\//');
        $result = $ast->accept($visitor);
        $this->assertNotEmpty($result);
    }

    // Test SampleGeneratorVisitor edge cases
    public function test_sample_generator_with_seed(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $generator->setSeed(12345);

        $regex = Regex::create();
        $ast = $regex->parse('/[a-z]/');
        $result = $ast->accept($generator);
        $this->assertNotEmpty($result);
    }

    public function test_sample_generator_reset_seed(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $generator->setSeed(12345);
        $generator->resetSeed();

        $regex = Regex::create();
        $ast = $regex->parse('/[a-z]/');
        $result = $ast->accept($generator);
        $this->assertNotEmpty($result);
    }

    public function test_sample_generator_unicode_prop(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $regex = Regex::create();

        // Test \p{L}
        $ast = $regex->parse('/\p{L}/');
        $result = $ast->accept($generator);
        $this->assertNotEmpty($result);
    }

    public function test_sample_generator_backref_named(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $regex = Regex::create();

        $ast = $regex->parse('/(?<name>abc)\k<name>/');
        $result = $ast->accept($generator);
        $this->assertStringContainsString('abc', $result);
    }

    public function test_sample_generator_group_non_capturing(): void
    {
        $generator = new SampleGeneratorNodeVisitor();
        $regex = Regex::create();

        $ast = $regex->parse('/(?:abc)/');
        $result = $ast->accept($generator);
        $this->assertStringContainsString('abc', $result);
    }
}
