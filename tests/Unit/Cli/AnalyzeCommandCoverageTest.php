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

namespace RegexParser\Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Cli\Command\AnalyzeCommand;
use RegexParser\Cli\ConsoleStyle;
use RegexParser\Cli\Output;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSConfidence;
use RegexParser\ReDoS\ReDoSMode;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\ValidationResult;

final class AnalyzeCommandCoverageTest extends TestCase
{
    #[Test]
    public function test_analyze_command_reports_a_redos_analysis_that_could_not_finish(): void
    {
        $analysis = new ReDoSAnalysis(
            ReDoSSeverity::UNKNOWN,
            0,
            null,
            ['Analysis incomplete: out of steam'],
            'RuntimeException: out of steam',
            null,
            null,
            ReDoSConfidence::LOW,
            null,
            [],
            null,
            null,
            [],
            ReDoSMode::THEORETICAL,
            null,
        );

        $buffer = $this->render($analysis);

        $this->assertStringContainsString('ReDoS error: RuntimeException: out of steam', $buffer);
    }

    #[Test]
    public function test_a_finished_analysis_reports_no_error(): void
    {
        $analysis = new ReDoSAnalysis(
            ReDoSSeverity::SAFE,
            0,
            null,
            [],
            null,
            null,
            null,
            ReDoSConfidence::LOW,
            null,
            [],
            null,
            null,
            [],
            ReDoSMode::THEORETICAL,
            null,
        );

        $this->assertStringNotContainsString('ReDoS error:', $this->render($analysis));
    }

    private function render(ReDoSAnalysis $analysis): string
    {
        $command = new AnalyzeCommand();
        $output = new Output(false, false);
        $style = new ConsoleStyle($output, false);

        $method = (new \ReflectionClass($command))->getMethod('renderConsoleOutput');

        ob_start();
        $method->invoke($command, $output, $style, '/foo/', new ValidationResult(true, null, 0), $analysis, '');

        return (string) ob_get_clean();
    }
}
