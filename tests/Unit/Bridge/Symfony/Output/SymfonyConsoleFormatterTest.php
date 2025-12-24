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

        $this->assertInstanceOf(\RegexParser\Bridge\Symfony\Output\SymfonyConsoleFormatter::class, $formatter);
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
}
