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

use PHPUnit\Framework\Attributes\DataProvider;
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
use RegexParser\Tests\Support\LintFunctionOverrides;

final class LintCommandCoverageTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        LintFunctionOverrides::reset();

        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
    }

    public function test_lint_command_reports_argument_parse_error(): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(fn (): int => $command->run($this->makeInput(['--format']), $output), $exitCode);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Missing value for --format', $buffer);
        $this->assertStringContainsString('Usage: regex lint', $buffer);
    }

    public function test_lint_command_defaults_paths_when_empty(): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(fn (): int => $command->run($this->makeInput(['--format=bogus']), $output), $exitCode);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown format', $buffer);
    }

    public function test_lint_command_returns_error_for_invalid_regex_options(): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(fn (): int => $command->run($this->makeInput(['--format=bogus'], ['bogus' => true]), $output), $exitCode);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid option', $buffer);
    }

    #[DataProvider('verbosityFlagProvider')]
    public function test_lint_command_builds_output_config_for_verbosity(string $flag): void
    {
        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $this->captureOutput(fn (): int => $command->run($this->makeInput([$flag, '--format=bogus']), $output), $exitCode);

        $this->assertSame(1, $exitCode);
    }

    /**
     * @return \Iterator<int, array{string}>
     */
    public static function verbosityFlagProvider(): \Iterator
    {
        yield ['--quiet'];
        yield ['--verbose'];
        yield ['--debug'];
    }

    public function test_lint_command_outputs_empty_json_report_when_no_patterns(): void
    {
        $dir = $this->makeTempDir();

        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(fn (): int => $command->run($this->makeInput([
            $dir,
            '--format=json',
            '--no-redos',
            '--no-validate',
            '--no-optimize',
        ]), $output), $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"stats"', $buffer);
    }

    public function test_lint_command_progress_handles_empty_collection(): void
    {
        $dir = $this->makeTempDir();

        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(fn (): int => $command->run($this->makeInput([
            $dir,
            '--format=console',
            '--no-redos',
            '--no-validate',
            '--no-optimize',
        ]), $output), $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No regex patterns found', $buffer);
    }

    public function test_lint_command_reports_collection_and_analysis_times(): void
    {
        $dir = $this->makeTempDir();
        $this->writeFile($dir, 'sample.php', "<?php\npreg_match('/foo/', 'bar');\n");

        LintFunctionOverrides::queueMicrotime(0.0);
        LintFunctionOverrides::queueMicrotime(2.5);
        LintFunctionOverrides::queueMicrotime(3.0);
        LintFunctionOverrides::queueMicrotime(3.3);
        LintFunctionOverrides::queueMicrotime(4.5);

        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = $this->captureOutput(fn (): int => $command->run($this->makeInput([
            $dir,
            '--format=console',
            '--no-redos',
            '--no-validate',
            '--no-optimize',
            '--jobs=2',
        ]), $output), $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Collection:', $buffer);
    }

    public function test_lint_command_reports_collection_failure(): void
    {
        $dir = $this->makeTempDir();
        @chmod($dir, 0o000);

        $command = $this->makeLintCommand();
        $output = new Output(false, false);

        $exitCode = 0;
        $buffer = '';

        try {
            $buffer = $this->captureOutput(fn (): int => $command->run($this->makeInput([
                $dir,
                '--format=console',
                '--no-redos',
                '--no-validate',
                '--no-optimize',
            ]), $output), $exitCode);
        } finally {
            @chmod($dir, 0o777);
        }

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to collect patterns', $buffer);
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
     * @param array<int, string>   $args
     * @param array<string, mixed> $regexOptions
     */
    private function makeInput(array $args, array $regexOptions = []): Input
    {
        return new Input(
            'lint',
            $args,
            new GlobalOptions(false, false, false, true, null, null),
            $regexOptions,
        );
    }

    /**
     * @param callable(): int $callback
     */
    private function captureOutput(callable $callback, int &$exitCode): string
    {
        $level = ob_get_level();
        ob_start();
        $exitCode = $callback();

        $output = (string) ob_get_clean();

        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        return $output;
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().'/regex-parser-lint-'.bin2hex(random_bytes(4));
        @mkdir($dir, 0o777, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function writeFile(string $dir, string $name, string $contents): string
    {
        $path = $dir.'/'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
    }
}
