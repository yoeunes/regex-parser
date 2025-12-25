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

namespace RegexParser\Tests\Unit\Lint\Formatter;

use PHPUnit\Framework\TestCase;
use RegexParser\Lint\Formatter\ConsoleFormatter;
use RegexParser\Lint\Formatter\OutputConfiguration;
use RegexParser\Lint\RegexLintReport;
use RegexParser\OptimizationResult;

final class ConsoleFormatterTest extends TestCase
{
    private ConsoleFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ConsoleFormatter();
    }

    public function test_construct(): void
    {
        $formatter = new ConsoleFormatter();
    }

    public function test_construct_with_config(): void
    {
        $config = new OutputConfiguration(ansi: false);
        $formatter = new ConsoleFormatter(config: $config);
    }

    public function test_construct_with_analysis_service(): void
    {
        // Since RegexAnalysisService is final, we test with null
        $formatter = new ConsoleFormatter(null);
    }

    public function test_format_empty_report(): void
    {
        $report = new RegexLintReport([], ['errors' => 0, 'warnings' => 0, 'optimizations' => 0]);

        $output = $this->formatter->format($report);

        $this->assertSame('', $output);
    }

    public function test_format_quiet_verbosity(): void
    {
        $config = new OutputConfiguration(verbosity: OutputConfiguration::VERBOSITY_QUIET);
        $formatter = new ConsoleFormatter(config: $config);

        $report = new RegexLintReport([], ['errors' => 1, 'warnings' => 2, 'optimizations' => 3]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('FAIL: 1 invalid patterns, 2 warnings, 3 optimizations.', $output);
    }

    public function test_format_quiet_no_errors(): void
    {
        $config = new OutputConfiguration(verbosity: OutputConfiguration::VERBOSITY_QUIET);
        $formatter = new ConsoleFormatter(config: $config);

        $report = new RegexLintReport([], ['errors' => 0, 'warnings' => 2, 'optimizations' => 3]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('WARN: 2 warnings found, 3 optimizations available.', $output);
    }

    public function test_format_quiet_no_issues(): void
    {
        $config = new OutputConfiguration(verbosity: OutputConfiguration::VERBOSITY_QUIET);
        $formatter = new ConsoleFormatter(config: $config);

        $report = new RegexLintReport([], ['errors' => 0, 'warnings' => 0, 'optimizations' => 3]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('PASS: No issues found, 3 optimizations available.', $output);
    }

    public function test_get_summary(): void
    {
        $stats = ['errors' => 1, 'warnings' => 2, 'optimizations' => 3];

        $output = $this->formatter->getSummary($stats);

        $this->assertStringContainsString('FAIL', $output);
        $this->assertStringContainsString('1 invalid patterns', $output);
    }

    public function test_get_summary_warnings_only(): void
    {
        $stats = ['errors' => 0, 'warnings' => 2, 'optimizations' => 3];

        $output = $this->formatter->getSummary($stats);

        $this->assertStringContainsString('PASS', $output);
        $this->assertStringContainsString('2 warnings found', $output);
    }

    public function test_get_summary_no_issues(): void
    {
        $stats = ['errors' => 0, 'warnings' => 0, 'optimizations' => 3];

        $output = $this->formatter->getSummary($stats);

        $this->assertStringContainsString('PASS', $output);
        $this->assertStringContainsString('No issues found', $output);
    }

    public function test_format_footer(): void
    {
        $output = $this->formatter->formatFooter();

        $this->assertStringContainsString('Star the repo', $output);
        $this->assertStringContainsString('github.com/yoeunes/regex-parser', $output);
    }

    public function test_format_with_error_issue(): void
    {
        $config = new OutputConfiguration(ansi: false);
        $formatter = new ConsoleFormatter(config: $config);

        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => '/test/',
            'issues' => [
                [
                    'type' => 'error',
                    'message' => 'Invalid regex pattern',
                    'file' => 'test.php',
                    'line' => 10,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('test.php:10', $output);
        $this->assertStringContainsString('/test/', $output);
        $this->assertStringContainsString('[FAIL]', $output);
        $this->assertStringContainsString('Invalid regex pattern', $output);
    }

    public function test_format_with_warning_issue(): void
    {
        $config = new OutputConfiguration(ansi: false);
        $formatter = new ConsoleFormatter(config: $config);

        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => '/test/',
            'issues' => [
                [
                    'type' => 'warning',
                    'message' => 'Complex pattern detected',
                    'hint' => 'Consider simplifying',
                    'file' => 'test.php',
                    'line' => 10,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new RegexLintReport([$result], ['errors' => 0, 'warnings' => 1, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('[WARN]', $output);
        $this->assertStringContainsString('Complex pattern detected', $output);
        $this->assertStringContainsString('Consider simplifying', $output);
    }

    public function test_format_with_optimization(): void
    {
        $config = new OutputConfiguration(ansi: false);
        $formatter = new ConsoleFormatter(config: $config);

        $optimization = new OptimizationResult('/a+/', '/a++/', ['Made possessive']);

        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => '/a+/',
            'issues' => [],
            'optimizations' => [
                [
                    'file' => 'test.php',
                    'line' => 10,
                    'optimization' => $optimization,
                    'savings' => 1,
                ],
            ],
            'problems' => [],
        ];

        $report = new RegexLintReport([$result], ['errors' => 0, 'warnings' => 0, 'optimizations' => 1]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('[TIP]', $output);
        $this->assertStringContainsString('- /a+/', $output);
        $this->assertStringContainsString('+ /a++/', $output);
    }

    public function test_format_without_ansi(): void
    {
        $config = new OutputConfiguration(ansi: false);
        $formatter = new ConsoleFormatter(config: $config);

        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => '/test/',
            'issues' => [
                [
                    'type' => 'error',
                    'message' => 'Invalid pattern',
                    'file' => 'test.php',
                    'line' => 10,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('test.php:10', $output);
        $this->assertStringContainsString('/test/', $output);
        $this->assertStringContainsString('[FAIL]', $output);
    }

    public function test_format_with_location(): void
    {
        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => '/test/',
            'location' => 'in function preg_match',
            'issues' => [
                [
                    'type' => 'error',
                    'message' => 'Invalid pattern',
                    'file' => 'test.php',
                    'line' => 10,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $this->formatter->format($report);

        $this->assertStringContainsString('↳ in function preg_match', $output);
    }

    public function test_format_extended_optimization(): void
    {
        $config = new OutputConfiguration(ansi: false);
        $formatter = new ConsoleFormatter(config: $config);

        $original = "/(?x)\n# This is a comment\n[a-z]+\n# Another comment\n/";
        $optimized = '/(?x)[a-z]++/';

        $optimization = new OptimizationResult($original, $optimized, ['Removed comments']);

        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => $original,
            'issues' => [],
            'optimizations' => [
                [
                    'file' => 'test.php',
                    'line' => 10,
                    'optimization' => $optimization,
                    'savings' => 10,
                ],
            ],
            'problems' => [],
        ];

        $report = new RegexLintReport([$result], ['errors' => 0, 'warnings' => 0, 'optimizations' => 1]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('[TIP]', $output);
        $this->assertStringContainsString('- /(?x)', $output);
        $this->assertStringContainsString('+ /(?x)[a-z]++/', $output);
    }
}
