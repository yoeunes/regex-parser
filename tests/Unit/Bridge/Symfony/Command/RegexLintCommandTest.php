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
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '';
        $this->assertStringContainsString(
            'Invalid format \'invalid\'. Supported formats: console, json, github, checkstyle, junit',
            $display,
        );
    }

    public function test_normalize_string_list_filters_invalid_values(): void
    {
        $command = $this->createCommand();

        // Test the private method through reflection
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('normalizeStringList');

        $this->assertSame([], $method->invoke($command, null));
        $this->assertSame([], $method->invoke($command, 'string'));
        $this->assertSame(['a', 'b'], $method->invoke($command, ['a', '', 'b', null, 123]));
    }

    public function test_sort_results_by_file_and_line(): void
    {
        $command = $this->createCommand();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('sortResultsByFileAndLine');

        $results = [
            ['file' => 'b.php', 'line' => 10],
            ['file' => 'a.php', 'line' => 5],
            ['file' => 'a.php', 'line' => 1],
        ];

        /** @var array<array{file: string, line: int}> $sorted */
        $sorted = $method->invoke($command, $results);

        $this->assertSame('a.php', $sorted[0]['file']);
        $this->assertSame(1, $sorted[0]['line']);
        $this->assertSame('a.php', $sorted[1]['file']);
        $this->assertSame(5, $sorted[1]['line']);
        $this->assertSame('b.php', $sorted[2]['file']);
        $this->assertSame(10, $sorted[2]['line']);
    }

    public function test_show_banner_outputs_correct_format(): void
    {
        $command = $this->createCommand();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('showBanner');

        $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
        $io->expects($this->exactly(2))->method('newLine');
        $io->expects($this->once())->method('writeln')
            ->with('  <fg=white;options=bold>Regex Parser</> <fg=gray>linting...</>');

        $method->invoke($command, $io);
    }

    public function test_show_footer_outputs_correct_format(): void
    {
        $command = $this->createCommand();

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('showFooter');

        $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
        $io->expects($this->exactly(2))->method('newLine');
        $io->expects($this->once())->method('writeln')
            ->with('  <fg=gray>Star the repo: https://github.com/yoeunes/regex-parser</>');

        $method->invoke($command, $io);
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
            formatterRegistry: new \RegexParser\Lint\Formatter\FormatterRegistry(),
            editorUrl: null,
        );
    }
}
