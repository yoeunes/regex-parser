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
use RegexParser\Node;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\DumperNodeVisitor;
use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\NodeVisitor\HighlighterVisitor;
use RegexParser\NodeVisitor\HtmlHighlighterVisitor;
use RegexParser\NodeVisitor\HtmlExplainNodeVisitor;
use RegexParser\NodeVisitor\MermaidNodeVisitor;
use RegexParser\NodeVisitor\MetricsNodeVisitor;
use RegexParser\OptimizationResult;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSConfidence;
use RegexParser\ReDoS\ReDoSFinding;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

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
            new ReDoSFinding(
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

    public function test_compiler_visitor_script_run(): void
    {
        $visitor = new CompilerNodeVisitor();
        $node = new Node\ScriptRunNode('Latin', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_compiler_visitor_limit_match(): void
    {
        $visitor = new CompilerNodeVisitor();
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_compiler_visitor_version_condition(): void
    {
        $visitor = new CompilerNodeVisitor();
        $node = new Node\VersionConditionNode('>=', '10.0', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_compiler_visitor_class_operation(): void
    {
        $visitor = new CompilerNodeVisitor();
        $left = new Node\LiteralNode('a', 0, 1);
        $right = new Node\LiteralNode('b', 2, 3);
        $node = new Node\ClassOperationNode(Node\ClassOperationType::INTERSECTION, $left, $right, 0, 3);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_dumper_visitor_script_run(): void
    {
        $visitor = new DumperNodeVisitor();
        $node = new Node\ScriptRunNode('Latin', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_dumper_visitor_limit_match(): void
    {
        $visitor = new DumperNodeVisitor();
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_dumper_visitor_version_condition(): void
    {
        $visitor = new DumperNodeVisitor();
        $node = new Node\VersionConditionNode('>=', '10.0', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_dumper_visitor_class_operation(): void
    {
        $visitor = new DumperNodeVisitor();
        $left = new Node\LiteralNode('a', 0, 1);
        $right = new Node\LiteralNode('b', 2, 3);
        $node = new Node\ClassOperationNode(Node\ClassOperationType::INTERSECTION, $left, $right, 0, 3);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_explain_visitor_script_run(): void
    {
        $visitor = new ExplainNodeVisitor();
        $node = new Node\ScriptRunNode('Latin', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_explain_visitor_limit_match(): void
    {
        $visitor = new ExplainNodeVisitor();
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_explain_visitor_version_condition(): void
    {
        $visitor = new ExplainNodeVisitor();
        $node = new Node\VersionConditionNode('>=', '10.0', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_explain_visitor_class_operation(): void
    {
        $visitor = new ExplainNodeVisitor();
        $left = new Node\LiteralNode('a', 0, 1);
        $right = new Node\LiteralNode('b', 2, 3);
        $node = new Node\ClassOperationNode(Node\ClassOperationType::INTERSECTION, $left, $right, 0, 3);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_highlighter_visitor_limit_match(): void
    {
        $visitor = new class extends HighlighterVisitor {
            protected function wrap(string $content, string $type): string
            {
                return "<span class=\"{$type}\">{$content}</span>";
            }
            protected function escape(string $string): string
            {
                return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_highlighter_visitor_callout(): void
    {
        $visitor = new class extends HighlighterVisitor {
            protected function wrap(string $content, string $type): string
            {
                return "<span class=\"{$type}\">{$content}</span>";
            }
            protected function escape(string $string): string
            {
                return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        $node = new Node\CalloutNode(1, false, 0, 4);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_highlighter_visitor_script_run(): void
    {
        $visitor = new class extends HighlighterVisitor {
            protected function wrap(string $content, string $type): string
            {
                return "<span class=\"{$type}\">{$content}</span>";
            }
            protected function escape(string $string): string
            {
                return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        $node = new Node\ScriptRunNode('Latin', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_highlighter_visitor_version_condition(): void
    {
        $visitor = new class extends HighlighterVisitor {
            protected function wrap(string $content, string $type): string
            {
                return "<span class=\"{$type}\">{$content}</span>";
            }
            protected function escape(string $string): string
            {
                return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        $node = new Node\VersionConditionNode('>=', '10.0', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_highlighter_visitor_unicode_extended_ascii(): void
    {
        $visitor = new class extends HighlighterVisitor {
            protected function wrap(string $content, string $type): string
            {
                return "<span class=\"{$type}\">{$content}</span>";
            }
            protected function escape(string $string): string
            {
                return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        };
        $node = new Node\UnicodeNode(chr(0x80), 0, 4);
        $result = $node->accept($visitor);
        $this->assertStringContainsString('\\x80', $result);
    }

    public function test_html_highlighter_visitor_dot(): void
    {
        $visitor = new HtmlHighlighterVisitor();
        $node = new Node\DotNode(1, 2);
        $result = $visitor->visitDot($node);
        $this->assertNotEmpty($result);
    }

    public function test_html_highlighter_visitor_unicode(): void
    {
        $visitor = new HtmlHighlighterVisitor();
        $node = new Node\UnicodeNode('1F600', 0, 7);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_highlighter_visitor_limit_match(): void
    {
        $visitor = new HtmlHighlighterVisitor();
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_highlighter_visitor_callout(): void
    {
        $visitor = new HtmlHighlighterVisitor();
        $node = new Node\CalloutNode(1, false, 0, 4);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_highlighter_visitor_script_run(): void
    {
        $visitor = new HtmlHighlighterVisitor();
        $node = new Node\ScriptRunNode('Latin', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_highlighter_visitor_version_condition(): void
    {
        $visitor = new HtmlHighlighterVisitor();
        $node = new Node\VersionConditionNode('>=', '10.0', 0, 18);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_visitor_unicode(): void
    {
        $visitor = new HtmlExplainNodeVisitor();
        $node = new Node\UnicodeNode('1F600', 0, 7);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_html_explain_visitor_limit_match(): void
    {
        $visitor = new HtmlExplainNodeVisitor();
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_mermaid_visitor_unicode(): void
    {
        $visitor = new MermaidNodeVisitor();
        $node = new Node\UnicodeNode('1F600', 0, 7);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_mermaid_visitor_limit_match(): void
    {
        $visitor = new MermaidNodeVisitor();
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_metrics_visitor_unicode(): void
    {
        $visitor = new MetricsNodeVisitor();
        $node = new Node\UnicodeNode('1F600', 0, 7);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }

    public function test_metrics_visitor_limit_match(): void
    {
        $visitor = new MetricsNodeVisitor();
        $node = new Node\LimitMatchNode(1000, 0, 16);
        $result = $node->accept($visitor);
        $this->assertNotEmpty($result);
    }
}
