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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Command;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Command\RegexLintCommand;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintService;
use RegexParser\Lint\RegexPatternSourceCollection;
use RegexParser\Regex;
use Symfony\Component\Console\Tester\CommandTester;

final class RegexLintCommandTest extends TestCase
{
    public function test_command_succeeds_by_default_with_no_patterns(): void
    {
        $command = $this->createCommand();

        $tester = new CommandTester($command);
        $status = $tester->execute(['paths' => ['nonexistent']]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('No regex patterns found', $tester->getDisplay());
    }

    public function test_command_has_correct_name(): void
    {
        $command = $this->createCommand();

        $this->assertSame('regex:lint', $command->getName());
    }

    public function test_command_has_all_expected_options(): void
    {
        $command = $this->createCommand();

        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('paths'));
        $this->assertTrue($definition->hasOption('exclude'));
        $this->assertTrue($definition->hasOption('min-savings'));
        $this->assertTrue($definition->hasOption('no-routes'));
        $this->assertTrue($definition->hasOption('no-validators'));
        $this->assertTrue($definition->hasOption('format'));

        $this->assertFalse($definition->hasOption('analyze-redos'));
        $this->assertFalse($definition->hasOption('optimize'));
    }

    public function test_json_format_outputs_raw_json(): void
    {
        $command = $this->createCommand();

        $tester = new CommandTester($command);
        $status = $tester->execute(['paths' => ['nonexistent'], '--format' => 'json']);

        $this->assertSame(0, $status);

        $output = $tester->getDisplay();
        $this->assertStringNotContainsString('No regex patterns found', $output);
        $this->assertStringNotContainsString('Regex Parser', $output);

        // Should be valid JSON
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('results', $data);
        $this->assertSame(['errors' => 0, 'warnings' => 0, 'optimizations' => 0], $data['stats']);
        $this->assertSame([], $data['results']);
    }

    public function test_invalid_format_returns_error(): void
    {
        $command = $this->createCommand();

        $tester = new CommandTester($command);
        $status = $tester->execute(['paths' => ['nonexistent'], '--format' => 'invalid']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Invalid format', $tester->getDisplay());
    }

    private function createCommand(): RegexLintCommand
    {
        $analysis = new RegexAnalysisService(Regex::create());
        $lint = new RegexLintService(
            $analysis,
            new RegexPatternSourceCollection([]),
        );

        return new RegexLintCommand(
            lint: $lint,
            analysis: $analysis,
            editorUrl: null,
        );
    }
}
