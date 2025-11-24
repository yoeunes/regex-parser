<?php

declare(strict_types=1);

namespace Yoeunes\RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;
use RegexParser\ReDoSSeverity;

final class ReDoSEdgeCasesTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    public function testSafePatternSingleQuantifier(): void
    {
        $analysis = $this->regex->analyzeReDoS('/a+b/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function testSafePatternCharacterClassWithLiteral(): void
    {
        $analysis = $this->regex->analyzeReDoS('/[a-z]+test/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function testSafePatternSimpleAlternation(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a|b)+c/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function testSafePatternNonCapturingAlternation(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:foo|bar)*baz/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function testSafePatternWordChars(): void
    {
        $analysis = $this->regex->analyzeReDoS('/\w+@\w+/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function testSafePatternBoundedNestedQuantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a{1,5})+/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
    }

    public function testSafePatternAnchoredQuantifier(): void
    {
        $analysis = $this->regex->analyzeReDoS('/^[a-z]*$/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotSame(ReDoSSeverity::HIGH, $analysis->severity);
    }

    public function testDangerousPatternNestedPlusQuantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a+)+b/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Nested quantifiers (a+)+ should be flagged as dangerous');
    }

    public function testDangerousPatternNestedStarQuantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a*)*b/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Nested quantifiers (a*)* should be flagged as dangerous');
    }

    public function testDangerousPatternOverlappingAlternation(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a|a)*/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Overlapping alternation (a|a)* should be flagged as dangerous');
    }

    public function testDangerousPatternOverlappingAlternationPatterns(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:a|ab)*c/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::MEDIUM, ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Overlapping alternation (?:a|ab)* should be flagged');
    }

    public function testDangerousPatternNestedNonCapturing(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:a+)+b/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Nested quantifiers (?:a+)+ should be flagged as dangerous');
    }

    public function testEdgeCaseWithAnchors(): void
    {
        $withoutAnchors = $this->regex->analyzeReDoS('/(a+)+b/');
        $withAnchors = $this->regex->analyzeReDoS('/^(a+)+b$/');
        
        $this->assertContains($withoutAnchors->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL]);
        $this->assertContains($withAnchors->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL]);
    }

    public function testEdgeCaseBoundedNestedQuantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a{1,3})+b/');
        
        $this->assertNotSame(ReDoSSeverity::CRITICAL, $analysis->severity);
    }

    public function testEdgeCaseTripleNesting(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(?:(?:a+)+)+b/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Triple nested quantifiers should be flagged as dangerous');
    }

    public function testDangerousPatternAlternativeQuantifiersNested(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(a*|b*)+/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Alternation with quantifiers nested should be flagged');
    }

    public function testDangerousPatternDoubleNestedQuantifiers(): void
    {
        $analysis = $this->regex->analyzeReDoS('/(x+x+)+y/');
        
        $this->assertContains($analysis->severity, [ReDoSSeverity::HIGH, ReDoSSeverity::CRITICAL], 
            'Double nested quantifiers should be flagged');
    }
}
