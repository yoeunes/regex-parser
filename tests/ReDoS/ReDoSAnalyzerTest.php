<?php

declare(strict_types=1);

namespace RegexParser\Tests\ReDoS;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\ReDoSAnalyzer;
use RegexParser\ReDoSSeverity;

class ReDoSAnalyzerTest extends TestCase
{
    private ReDoSAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ReDoSAnalyzer();
    }

    #[DataProvider('patternProvider')]
    public function testSeverityAnalysis(string $pattern, ReDoSSeverity $expectedSeverity): void
    {
        $analysis = $this->analyzer->analyze($pattern);
        $this->assertSame($expectedSeverity, $analysis->severity, "Failed asserting severity for pattern: $pattern");
    }

    public static function patternProvider(): \Iterator
    {
        // SAFE
        yield ['/abc/', ReDoSSeverity::SAFE];
        yield ['/^\d{4}-\d{2}-\d{2}$/', ReDoSSeverity::SAFE];

        // LOW (Bounded nested)
        yield ['/(a{1,5}){1,5}/', ReDoSSeverity::LOW];

        // MEDIUM (Single unbounded)
        yield ['/a+/', ReDoSSeverity::MEDIUM];
        yield ['/.*ok/', ReDoSSeverity::MEDIUM];

        // HIGH (Nested unbounded)
        yield ['/(a+)+/', ReDoSSeverity::HIGH];
        yield ['/(.*)*/', ReDoSSeverity::CRITICAL];

        // CRITICAL (Overlapping alternation in loop)
        yield ['/(a|a)+/', ReDoSSeverity::CRITICAL];
        yield ['/(a|a)*/', ReDoSSeverity::CRITICAL];

        // Atomic groups (Mitigation)
        yield ['/(?>a+)+/', ReDoSSeverity::SAFE]; // Atomic prevents backtracking
        yield ['/a++/', ReDoSSeverity::SAFE]; // Possessive
    }

    public function testAnalysisDetails(): void
    {
        $analysis = $this->analyzer->analyze('/(a+)+/');

        // Le visiteur détecte une imbrication critique pour ce pattern spécifique
        $this->assertTrue(
            $analysis->severity === ReDoSSeverity::HIGH || $analysis->severity === ReDoSSeverity::CRITICAL,
            'Severity should be HIGH or CRITICAL'
        );

        $this->assertNotEmpty($analysis->recommendations);
    }
}
