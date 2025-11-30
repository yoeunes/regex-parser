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

namespace Yoeunes\RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\ReDoSSeverity;
use RegexParser\Regex;

class ReDoSEdgeCasesTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    public function test_safe_pattern_single_quantifier(): void
    {
        $analysis = $this->regex->analyzeReDoS('/a+b/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function test_safe_pattern_character_class_with_literal(): void
    {
        $analysis = $this->regex->analyzeReDoS('/[a-z]+test/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function test_safe_pattern_simple_alternation(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a|b)+c/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function test_safe_pattern_non_capturing_alternation(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:foo|bar)*baz/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function test_safe_pattern_word_chars(): void
    {
        $analysis = $this->regex->analyzeReDoS('/\w+@\w+/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function test_safe_pattern_bounded_nested_quantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a{1,5})+/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
    }

    public function test_safe_pattern_anchored_quantifier(): void
    {
        $analysis = $this->regex->analyzeReDoS('/^[a-z]*$/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function test_dangerous_pattern_nested_plus_quantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a+)+b/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Nested quantifiers (a+)+ should be flagged as dangerous');
    }

    public function test_dangerous_pattern_nested_star_quantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a*)*b/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Nested quantifiers (a*)* should be flagged as dangerous');
    }

    public function test_dangerous_pattern_overlapping_alternation(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a|a)*/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Overlapping alternation (a|a)* should be flagged as dangerous');
    }

    public function test_dangerous_pattern_overlapping_alternation_patterns(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:a|ab)*c/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::MEDIUM, ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Overlapping alternation (?:a|ab)* should be flagged');
    }

    public function test_dangerous_pattern_nested_non_capturing(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:a+)+b/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Nested quantifiers (?:a+)+ should be flagged as dangerous');
    }

    public function test_edge_case_with_anchors(): void
    {
        $withoutAnchors = $this->regex->analyzeReDoS('/(a+)+b/');
        $withAnchors = $this->regex->analyzeReDoS('/^(a+)+b$/');

        $this->assertContains($withoutAnchors->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL]);
        $this->assertContains($withAnchors->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL]);
    }

    public function test_edge_case_bounded_nested_quantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a{1,3})+b/');

        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
    }

    public function test_edge_case_triple_nesting(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:(?:a+)+)+b/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Triple nested quantifiers should be flagged as dangerous');
    }

    public function test_dangerous_pattern_alternative_quantifiers_nested(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a*|b*)+/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Alternation with quantifiers nested should be flagged');
    }

    public function test_dangerous_pattern_double_nested_quantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(x+x+)+y/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL],
            'Double nested quantifiers should be flagged');
    }
}
