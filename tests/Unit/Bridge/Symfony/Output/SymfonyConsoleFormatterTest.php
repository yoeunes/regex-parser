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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Output;

use PHPUnit\Framework\TestCase;

final class SymfonyConsoleFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Symfony\Component\Console\Formatter\OutputFormatter::class)) {
            $this->markTestSkipped('Symfony Console component is not available');
        }

        if (!class_exists(\RegexParser\Bridge\Symfony\Console\LinkFormatter::class)) {
            $this->markTestSkipped('LinkFormatter is not available');
        }
    }

    public function test_construct(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);
    }

    public function test_format_empty_report(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $report = new \RegexParser\Lint\RegexLintReport([], ['errors' => 0, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('PASS', $output);
        $this->assertStringContainsString('No issues found', $output);
    }

    public function test_format_error(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $result = 'Test error message';

        $output = $formatter->formatError($result);

        $this->assertSame($result, $output);
    }

    public function test_format_with_error_issues(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

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

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('test.php', $output);
        $this->assertStringContainsString('FAIL', $output);
        $this->assertStringContainsString('Invalid regex pattern', $output);
    }

    public function test_format_with_warning_issues(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

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

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 0, 'warnings' => 1, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('WARN', $output);
        $this->assertStringContainsString('Complex pattern detected', $output);
        $this->assertStringContainsString('Consider simplifying', $output);
    }

    public function test_format_with_info_issues(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

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

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 0, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('FAIL', $output);
    }

    public function test_format_with_optimizations(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $optimization = new \RegexParser\OptimizationResult('original', 'optimized');
        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => 'original',
            'issues' => [],
            'optimizations' => [
                [
                    'file' => 'test.php',
                    'line' => 10,
                    'optimization' => $optimization,
                    'savings' => 5,
                ],
            ],
            'problems' => [],
        ];

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 0, 'warnings' => 0, 'optimizations' => 1]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('TIP', $output);
        $this->assertStringContainsString('Optimization available', $output);
        $this->assertStringContainsString('original', $output);
        $this->assertStringContainsString('optimized', $output);
    }

    public function test_format_with_location(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $result = [
            'file' => 'test.php',
            'line' => 0,
            'pattern' => '/test/',
            'location' => 'in function call',
            'issues' => [
                [
                    'type' => 'error',
                    'message' => 'Invalid regex pattern',
                    'file' => 'test.php',
                    'line' => 0,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('in function call', $output);
    }

    public function test_format_with_multiple_files(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $result1 = [
            'file' => 'file1.php',
            'line' => 10,
            'pattern' => '/test1/',
            'issues' => [
                [
                    'type' => 'error',
                    'message' => 'Error in file1',
                    'file' => 'file1.php',
                    'line' => 10,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $result2 = [
            'file' => 'file2.php',
            'line' => 20,
            'pattern' => '/test2/',
            'issues' => [
                [
                    'type' => 'warning',
                    'message' => 'Warning in file2',
                    'file' => 'file2.php',
                    'line' => 20,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new \RegexParser\Lint\RegexLintReport([$result1, $result2], ['errors' => 1, 'warnings' => 1, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('file1.php', $output);
        $this->assertStringContainsString('file2.php', $output);
        $this->assertStringContainsString('Error in file1', $output);
        $this->assertStringContainsString('Warning in file2', $output);
    }

    public function test_format_with_multiline_message(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $result = [
            'file' => 'test.php',
            'line' => 10,
            'pattern' => '/test/',
            'issues' => [
                [
                    'type' => 'info',
                    'message' => 'Pattern information',
                    'file' => 'test.php',
                    'line' => 10,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('INFO', $output);
    }

    public function test_format_with_no_pattern(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $result = [
            'file' => 'test.php',
            'line' => 0,
            'location' => 'in function call',
            'pattern' => null,
            'issues' => [
                [
                    'type' => 'error',
                    'message' => 'Invalid regex pattern',
                    'file' => 'test.php',
                    'line' => 0,
                ],
            ],
            'optimizations' => [],
            'problems' => [],
        ];

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('(pattern unavailable)', $output);
    }

    public function test_format_with_decorated_false(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter, false);

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

        $report = new \RegexParser\Lint\RegexLintReport([$result], ['errors' => 1, 'warnings' => 0, 'optimizations' => 0]);

        $output = $formatter->format($report);

        // Should not contain ANSI escape codes when decorated is false
        $this->assertStringNotContainsString("\033[", $output);
        $this->assertStringContainsString('FAIL', $output);
    }

    public function test_format_summary_with_errors(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $report = new \RegexParser\Lint\RegexLintReport([], ['errors' => 2, 'warnings' => 1, 'optimizations' => 3]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('2 invalid patterns', $output);
        $this->assertStringContainsString('1 warnings, 3 optimizations', $output);
    }

    public function test_format_summary_with_warnings_only(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $report = new \RegexParser\Lint\RegexLintReport([], ['errors' => 0, 'warnings' => 2, 'optimizations' => 1]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('2 warnings found', $output);
        $this->assertStringContainsString('1 optimizations available', $output);
    }

    public function test_format_summary_with_optimizations_only(): void
    {
        $analysis = new \RegexParser\Lint\RegexAnalysisService(\RegexParser\Regex::create());
        $relativePathHelper = new \RegexParser\Bridge\Symfony\Console\RelativePathHelper();
        $linkFormatter = new \RegexParser\Bridge\Symfony\Console\LinkFormatter(null, $relativePathHelper);

        $formatter = new \RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter($analysis, $linkFormatter);

        $report = new \RegexParser\Lint\RegexLintReport([], ['errors' => 0, 'warnings' => 0, 'optimizations' => 3]);

        $output = $formatter->format($report);

        $this->assertStringContainsString('No issues found', $output);
        $this->assertStringContainsString('3 optimizations available', $output);
    }
}
