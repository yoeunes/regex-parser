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

namespace RegexParser\Tests\Functional\Lint;

use PHPUnit\Framework\TestCase;
use RegexParser\Cli\Command\HelpCommand;
use RegexParser\Cli\GlobalOptions;
use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Lint\Command\LintArgumentParser;
use RegexParser\Lint\Command\LintCommand;
use RegexParser\Lint\Command\LintConfigLoader;
use RegexParser\Lint\Command\LintDefaultsBuilder;
use RegexParser\Lint\Command\LintExtractorFactory;
use RegexParser\Lint\Command\LintOutputRenderer;

final class LintCommandTest extends TestCase
{
    public function test_lint_command_reports_invalid_config(): void
    {
        $cwd = getcwd();
        $tempDir = sys_get_temp_dir().'/regex-parser-lint-'.uniqid('', true);
        if (false === @mkdir($tempDir) && !is_dir($tempDir)) {
            $this->markTestSkipped('Unable to create temp directory.');
        }

        file_put_contents($tempDir.'/regex.json', '{');

        try {
            if (false === @chdir($tempDir)) {
                $this->markTestSkipped('Unable to change directory.');
            }

            $command = $this->makeLintCommand();
            $output = new Output(false, false);

            $exitCode = 0;
            $buffer = $this->captureOutput(static fn (): int => $command->run(self::makeInput([]), $output), $exitCode);

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('Invalid JSON', $buffer);
        } finally {
            if (is_dir($tempDir)) {
                @unlink($tempDir.'/regex.json');
                @rmdir($tempDir);
            }
            if (false !== $cwd) {
                @chdir($cwd);
            }
        }
    }

    public function test_lint_command_invokes_help(): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(static fn (): int => $command->run(self::makeInput(['--help']), $output), $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Usage', $buffer);
    }

    public function test_lint_command_rejects_unknown_format(): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(static fn (): int => $command->run(self::makeInput(['--format=bogus', self::fixturePath('simple_text.php')]), $output), $exitCode);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown format', $buffer);
    }

    public function test_lint_command_handles_empty_patterns(): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(static fn (): int => $command->run(self::makeInput([
            self::fixturePath('simple_text.php'),
            '--format=console',
            '--no-redos',
            '--no-validate',
            '--no-optimize',
        ]), $output), $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No regex patterns found', $buffer);
    }

    public function test_lint_command_outputs_json_report(): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(static fn (): int => $command->run(self::makeInput([
            self::fixturePath('valid_preg_match.php'),
            '--format=json',
            '--no-redos',
            '--no-validate',
            '--no-optimize',
        ]), $output), $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"stats"', $buffer);
        $this->assertStringContainsString('"results"', $buffer);
    }

    private function makeLintCommand(): LintCommand
    {
        $helpCommand = new HelpCommand();

        return new LintCommand(
            $helpCommand,
            new LintConfigLoader(),
            new LintDefaultsBuilder(),
            new LintArgumentParser(),
            new LintExtractorFactory(),
            new LintOutputRenderer(),
        );
    }

    /**
     * @param array<int, string> $args
     */
    private static function makeInput(array $args): Input
    {
        return new Input(
            'lint',
            $args,
            new GlobalOptions(false, false, false, true, null, null),
            [],
        );
    }

    private static function fixturePath(string $file): string
    {
        return __DIR__.'/../../Fixtures/Functional/'.$file;
    }

    /**
     * @param callable(): int $callback
     */
    private function captureOutput(callable $callback, int &$exitCode): string
    {
        ob_start();
        $exitCode = $callback();

        return (string) ob_get_clean();
    }
}
