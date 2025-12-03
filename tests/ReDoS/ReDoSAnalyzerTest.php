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

namespace RegexParser\Tests\ReDoS;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\ReDoS\ReDoSAnalyzer;
use RegexParser\ReDoS\ReDoSSeverity;

class ReDoSAnalyzerTest extends TestCase
{
    private ReDoSAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ReDoSAnalyzer();
    }

    #[DataProvider('patternProvider')]
    public function test_severity_analysis(string $pattern, ReDoSSeverity $expectedSeverity): void
    {
        $analysis = $this->analyzer->analyze($pattern);
        $this->assertSame($expectedSeverity, $analysis->severity, "Failed asserting severity for pattern: $pattern");
    }

    public static function patternProvider(): \Iterator
    {
        // SAFE
        yield ['/abc/', ReDoSSeverity::SAFE];
        yield ['/^\d{4}-\d{2}-\d{2}$/', ReDoSSeverity::SAFE];
        yield ['/^[a-z0-9]+(?:-[a-z0-9]+)*$/', ReDoSSeverity::SAFE];

        // LOW (Bounded nested)
        yield ['/(a{1,5}){1,5}/', ReDoSSeverity::LOW];

        // MEDIUM (Single unbounded)
        yield ['/a+/', ReDoSSeverity::MEDIUM];
        yield ['/.*ok/', ReDoSSeverity::MEDIUM];

        // HIGH (Nested unbounded)
        yield ['/(a+)+/', ReDoSSeverity::CRITICAL]; // Triggers Star Height > 1

        // CRITICAL (Overlapping alternation in loop)
        yield ['/(a|a)+/', ReDoSSeverity::CRITICAL];
        yield ['/(a|a)*/', ReDoSSeverity::CRITICAL];

        // Atomic groups (Mitigation)
        yield ['/(?>a+)+/', ReDoSSeverity::SAFE];
        yield ['/a++/', ReDoSSeverity::SAFE];
    }

    public function test_analysis_details(): void
    {
        $analysis = $this->analyzer->analyze('/(a+)+/');

        // The visitor detects critical nesting for this specific pattern
        $this->assertSame(ReDoSSeverity::CRITICAL, $analysis->severity);
        $this->assertNotEmpty($analysis->recommendations);
    }

    public function test_symfony_slug_pattern_is_treated_as_safe(): void
    {
        $analysis = $this->analyzer->analyze('/[a-z0-9]+(?:-[a-z0-9]+)*/');

        $this->assertContains($analysis->severity, [ReDoSSeverity::SAFE, ReDoSSeverity::LOW]);
    }
}
