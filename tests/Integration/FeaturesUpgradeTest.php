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
use RegexParser\ParserOptions;
use RegexParser\Regex;

/**
 * Integration tests for v1.0 upgrade features.
 *
 * Tests:
 * 1. ParserOptions with resource limits
 * 2. MermaidVisitor visualization
 */
class FeaturesUpgradeTest extends TestCase
{
    /**
     * Test ParserOptions DTO instantiation with defaults.
     */
    public function test_parser_options_defaults(): void
    {
        $options = new ParserOptions();

        $this->assertSame(10_000, $options->maxPatternLength);
        $this->assertSame(10_000, $options->maxNodes);
        $this->assertSame(250, $options->maxRecursionDepth);
    }

    /**
     * Test ParserOptions with custom values.
     */
    public function test_parser_options_custom(): void
    {
        $options = new ParserOptions(
            maxPatternLength: 5000,
            maxNodes: 500,
            maxRecursionDepth: 100,
        );

        $this->assertSame(5000, $options->maxPatternLength);
        $this->assertSame(500, $options->maxNodes);
        $this->assertSame(100, $options->maxRecursionDepth);
    }

    /**
     * Test ParserOptions::fromArray() factory.
     */
    public function test_parser_options_from_array(): void
    {
        $config = [
            'max_pattern_length' => 20_000,
            'max_nodes' => 5000,
            'max_recursion_depth' => 150,
        ];

        $options = ParserOptions::fromArray($config);

        $this->assertSame(20_000, $options->maxPatternLength);
        $this->assertSame(5000, $options->maxNodes);
        $this->assertSame(150, $options->maxRecursionDepth);
    }

    /**
     * Test MermaidVisitor generates valid Mermaid syntax.
     */
    public function test_mermaid_visitor_generates_valid_syntax(): void
    {
        $regex = Regex::create();
        $mermaidOutput = $regex->visualize('/abc/');

        // Should start with "graph TD;"
        $this->assertStringStartsWith('graph TD;', $mermaidOutput);

        // Should contain node definitions
        $this->assertStringContainsString('node', $mermaidOutput);

        // Should contain connection arrows
        $this->assertStringContainsString('-->', $mermaidOutput);
    }

    /**
     * Test MermaidVisitor output for simple pattern.
     */
    public function test_mermaid_visitor_simple_pattern(): void
    {
        $regex = Regex::create();
        $mermaidOutput = $regex->visualize('/test/');

        $this->assertStringStartsWith('graph TD;', $mermaidOutput);
        $this->assertStringContainsString('Regex:', $mermaidOutput);
        $this->assertStringContainsString('Literal:', $mermaidOutput);
    }

    /**
     * Test MermaidVisitor with complex pattern (group + quantifier).
     */
    public function test_mermaid_visitor_complex_pattern(): void
    {
        $regex = Regex::create();
        $mermaidOutput = $regex->visualize('/(abc)+/');

        $this->assertStringStartsWith('graph TD;', $mermaidOutput);
        $this->assertStringContainsString('Group:', $mermaidOutput);
        $this->assertStringContainsString('Quantifier:', $mermaidOutput);
    }

    /**
     * Test Regex::visualize() integrates with parser.
     */
    public function test_regex_visualize_integration(): void
    {
        $regex = Regex::create();

        $visualization = $regex->visualize('/[a-z]+/');

        $this->assertIsString($visualization);
        $this->assertStringStartsWith('graph TD;', $visualization);
        $this->assertStringContainsString('CharClass', $visualization);
    }

    /**
     * Test Regex::visualize() with multiple alternatives.
     */
    public function test_regex_visualize_alternation(): void
    {
        $regex = Regex::create();

        $visualization = $regex->visualize('/(cat|dog|bird)/');

        $this->assertStringStartsWith('graph TD;', $visualization);
        $this->assertStringContainsString('Alternation', $visualization);
    }

    /**
     * Test ParserOptions prevents DoS via pattern length.
     */
    public function test_parser_options_enforces_pattern_length(): void
    {
        $options = new ParserOptions(maxPatternLength: 5);
        $parser = new Parser(['max_pattern_length' => 5]);

        $this->expectException(\Exception::class);

        // Pattern longer than 5 characters should fail
        $parser->parse('/this_is_too_long/');
    }

    /**
     * Test MermaidVisitor handles all node types.
     */
    public function test_mermaid_visitor_comprehensive_node_types(): void
    {
        $regex = Regex::create();

        // Complex pattern with multiple node types
        $patterns = [
            '/^test$/',                     // Anchors
            '/[a-z]+/',                     // CharClass
            '/\d{2,5}/',                    // Quantifier with range
            '/(abc)/',                      // Group
            '/(a|b)/',                      // Alternation
            '/(?<=abc)def/',                // Lookbehind
            '/test(?!ing)/',                // Lookahead
            '/(?P<name>\w+)/',              // Named group
        ];

        foreach ($patterns as $pattern) {
            $visualization = $regex->visualize($pattern);

            $this->assertStringStartsWith('graph TD;', $visualization);
            $this->assertStringContainsString('-->', $visualization);
            $this->assertStringContainsString('node', $visualization);
        }
    }
}
