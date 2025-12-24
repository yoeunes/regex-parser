<?php

declare(strict_types=1);

/*
 * This file is part of RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\AnalysisReport;
use RegexParser\OptimizationResult;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSConfidence;
use RegexParser\ReDoS\ReDoSFindings;
use RegexParser\ReDoS\ReDoSSeverity;

final class AdditionalMissingCoverageTest extends TestCase
{
    public function test_analysis_report_optimizations(): void
    {
        $optimizations = new OptimizationResult('/a+b/', '/a+b/', []);
        $redos = new ReDoSAnalysis(ReDoSSeverity::SAFE, 0);

        $report = new AnalysisReport(
            isValid: true,
            errors: [],
            lintIssues: [],
            redos: $redos,
            optimizations: $optimizations,
            explain: 'Test explanation',
            highlighted: '<span>Test</span>'
        );

        $this->assertSame($optimizations, $report->optimizations());
        $this->assertSame('Test explanation', $report->explain());
        $this->assertSame('<span>Test</span>', $report->highlighted());
    }

    public function test_redos_analysis_get_vulnerable_subpattern_with_both(): void
    {
        $analysis = new ReDoSAnalysis(
            ReDoSSeverity::HIGH,
            8,
            vulnerablePart: '(a+)+',
            vulnerableSubpattern: 'nested',
            trigger: 'aaaaaaaaab',
            confidence: ReDoSConfidence::HIGH,
            findings: []
        );

        $this->assertSame('nested', $analysis->getVulnerableSubpattern());
    }

    public function test_redos_analysis_get_vulnerable_subpattern_with_part_only(): void
    {
        $analysis = new ReDoSAnalysis(
            ReDoSSeverity::HIGH,
            8,
            vulnerablePart: '(a+)+',
            vulnerableSubpattern: null,
            trigger: 'aaaaaaaaab',
            confidence: ReDoSConfidence::HIGH,
            findings: []
        );

        $this->assertSame('(a+)+', $analysis->getVulnerableSubpattern());
    }

    public function test_redos_analysis_get_vulnerable_subpattern_null(): void
    {
        $analysis = new ReDoSAnalysis(
            ReDoSSeverity::SAFE,
            0,
            vulnerablePart: null,
            vulnerableSubpattern: null
        );

        $this->assertNull($analysis->getVulnerableSubpattern());
    }

    public function test_redos_analysis_with_findings(): void
    {
        $findings = [
            new ReDoSFindings(
                ReDoSSeverity::HIGH,
                'nested quantifiers',
                '(a+)+',
                'aaaaaaaaab'
            ),
        ];

        $analysis = new ReDoSAnalysis(
            ReDoSSeverity::HIGH,
            8,
            vulnerablePart: '(a+)+',
            findings: $findings,
            confidence: ReDoSConfidence::HIGH
        );

        $this->assertCount(1, $analysis->findings);
        $this->assertSame('nested quantifiers', $analysis->findings[0]->message);
    }

    public function test_redos_analysis_with_suggested_rewrite(): void
    {
        $analysis = new ReDoSAnalysis(
            ReDoSSeverity::HIGH,
            8,
            vulnerablePart: '(a+)+',
            suggestedRewrite: '(?:a+)+',
            recommendations: ['Use atomic group']
        );

        $this->assertSame('(?:a+)+', $analysis->suggestedRewrite);
        $this->assertSame(['Use atomic group'], $analysis->recommendations);
    }
}
