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

namespace RegexParser\Cli\Command;

use RegexParser\Cli\Input;
use RegexParser\Cli\Output;
use RegexParser\Cli\VersionResolver;

final readonly class HelpCommand implements CommandInterface
{
    public function __construct(private VersionResolver $versionResolver) {}

    public function getName(): string
    {
        return 'help';
    }

    public function getAliases(): array
    {
        return ['--help', '-h'];
    }

    public function getDescription(): string
    {
        return 'Display this help message';
    }

    public function run(Input $input, Output $output): int
    {
        $this->renderHeader($output);

        $this->renderTextSection($output, 'Description', [
            'CLI for regex parsing, validation, analysis, and linting',
        ]);

        $this->renderTextSection($output, 'Usage', [
            $this->formatUsage($output),
        ]);

        $commands = [
            ['parse', 'Parse and recompile a regex pattern'],
            ['analyze', 'Parse, validate, and analyze ReDoS risk'],
            ['debug', 'Deep ReDoS analysis with heatmap output'],
            ['diagram', 'Render an ASCII diagram of the AST'],
            ['highlight', 'Highlight a regex for display'],
            ['validate', 'Validate a regex pattern'],
            ['lint', 'Lint regex patterns in PHP source code'],
            ['self-update', 'Update the CLI phar to the latest release'],
            ['help', 'Display this help message'],
        ];
        $this->renderTableSection($output, 'Commands', $commands, fn (string $value): string => $this->formatCommand($output, $value));

        $globalOptions = [
            ['--ansi', 'Force ANSI output'],
            ['--no-ansi', 'Disable ANSI output'],
            ['-q, --quiet', 'Suppress output'],
            ['--silent', 'Same as --quiet'],
            ['--php-version <ver>', 'Target PHP version for validation'],
            ['--help', 'Display this help message'],
        ];
        $this->renderTableSection($output, 'Global Options', $globalOptions, fn (string $value): string => $this->formatOption($output, $value));

        $lintOptions = [
            ['--exclude <path>', 'Paths to exclude (repeatable)'],
            ['--min-savings <n>', 'Minimum optimization savings'],
            ['--jobs <n>', 'Parallel workers for analysis'],
            ['--format <format>', 'Output format (console, json, github, checkstyle, junit)'],
            ['--no-redos', 'Skip ReDoS risk analysis'],
            ['--no-validate', 'Skip validation errors (structural lint only)'],
            ['--no-optimize', 'Disable optimization suggestions'],
            ['-v, --verbose', 'Show detailed output'],
            ['--debug', 'Show debug information'],
        ];
        $this->renderTableSection($output, 'Lint Options', $lintOptions, fn (string $value): string => $this->formatOption($output, $value));
        $output->write($output->dim('  Config: regex.json or regex.dist.json in the working directory sets lint defaults.')."\n");
        $output->write($output->dim('  Inline ignore: // @regex-ignore-next-line or // @regex-ignore')."\n\n");

        $diagramOptions = [
            ['--format <format>', 'Output format (ascii)'],
        ];
        $this->renderTableSection($output, 'Diagram Options', $diagramOptions, fn (string $value): string => $this->formatOption($output, $value));

        $debugOptions = [
            ['--input <string>', 'Input string to test against the pattern'],
        ];
        $this->renderTableSection($output, 'Debug Options', $debugOptions, fn (string $value): string => $this->formatOption($output, $value));

        $examples = [
            [['regex', "'/a+/'"], 'Quick highlight'],
            [['regex', 'parse', "'/a+/'", '--validate'], 'Parse with validation'],
            [['regex', 'analyze', "'/a+/'"], 'Full analysis'],
            [['regex', 'diagram', "'/^a+$/'"], 'ASCII diagram'],
            [['regex', 'debug', "'/(a+)+$/'"], 'Heatmap + ReDoS details'],
            [['regex', 'highlight', "'/a+/'", '--format=html'], 'HTML highlight'],
            [['regex', 'lint', 'src/', '--exclude=vendor'], 'Lint a codebase'],
            [['regex', 'lint', '--format=json', 'src/'], 'JSON output'],
            [['regex', 'lint', '--verbose', 'src/'], 'Verbose output'],
            [['regex', 'self-update'], 'Update the installed phar'],
        ];
        $this->renderExamplesSection($output, $examples);

        return 0;
    }

    private function renderHeader(Output $output): void
    {
        $version = $this->versionResolver->resolve('dev') ?? 'dev';

        if ($this->shouldAnimate($output)) {
            $this->renderAnimatedHeader($output, $version);

            return;
        }

        $output->write($this->formatHeaderLine($output, $this->renderStaticName($output), $version)."\n");
        $output->write($output->dim('Treat Regular Expressions as Code.')."\n\n");
    }

    private function renderAnimatedHeader(Output $output, string $version): void
    {
        $name = 'RegexParser';
        $frames = [0, 2, 4, 6, 8, 10];

        foreach ($frames as $index) {
            $line = $this->formatHeaderLine($output, $this->renderNameFrame($output, $name, $index), $version);
            $output->write("\r\033[2K".$line);
            usleep(18000);
            if (\function_exists('fflush')) {
                fflush(\STDOUT);
            }
        }

        $output->write("\r\033[2K".$this->formatHeaderLine($output, $this->renderStaticName($output), $version)."\n");
        $output->write($output->dim('Treat Regular Expressions as Code.')."\n\n");
    }

    private function shouldAnimate(Output $output): bool
    {
        if (!$output->isAnsi() || $output->isQuiet()) {
            return false;
        }

        if (false !== getenv('CI')) {
            return false;
        }

        if (!\function_exists('posix_isatty')) {
            return false;
        }

        return posix_isatty(\STDOUT);
    }

    private function formatHeaderLine(Output $output, string $name, string $version): string
    {
        return $name.' '.$output->warning($version).' by Younes ENNAJI';
    }

    private function renderStaticName(Output $output): string
    {
        return $output->color('RegexParser', Output::CYAN.Output::BOLD);
    }

    private function renderNameFrame(Output $output, string $name, int $highlightIndex): string
    {
        $characters = str_split($name);
        $result = '';

        foreach ($characters as $index => $character) {
            if ($index === $highlightIndex) {
                $result .= $output->color($character, Output::MAGENTA.Output::BOLD);
            } elseif ($index === $highlightIndex - 1 || $index === $highlightIndex + 1) {
                $result .= $output->color($character, Output::CYAN.Output::BOLD);
            } else {
                $result .= $output->color($character, Output::CYAN);
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $lines
     */
    private function renderTextSection(Output $output, string $title, array $lines): void
    {
        $output->write($output->color($title.':', Output::MAGENTA)."\n");

        foreach ($lines as $line) {
            $output->write('  '.$line."\n");
        }

        $output->write("\n");
    }

    /**
     * @param array<int, array{0: string, 1: string}> $rows
     * @param callable(string): string                $formatLeft
     */
    private function renderTableSection(Output $output, string $title, array $rows, callable $formatLeft): void
    {
        $output->write($output->color($title.':', Output::MAGENTA)."\n");
        $this->renderTable($output, $rows, $formatLeft);
        $output->write("\n");
    }

    /**
     * @param array<int, array{0: string, 1: string}> $rows
     * @param callable(string): string                $formatLeft
     */
    private function renderTable(Output $output, array $rows, callable $formatLeft): void
    {
        $maxWidth = 0;
        foreach ($rows as $row) {
            $maxWidth = max($maxWidth, \strlen($row[0]));
        }

        foreach ($rows as [$left, $right]) {
            $padding = max(0, $maxWidth - \strlen($left));
            $output->write('  '.$formatLeft($left).str_repeat(' ', $padding + 2).$right."\n");
        }
    }

    /**
     * @param array<int, array{0: array<int, string>, 1: string}> $examples
     */
    private function renderExamplesSection(Output $output, array $examples): void
    {
        $output->write($output->color('Examples:', Output::MAGENTA)."\n");

        $maxWidth = 0;
        foreach ($examples as [$tokens]) {
            $command = implode(' ', $tokens);
            $maxWidth = max($maxWidth, \strlen($command));
        }

        foreach ($examples as [$tokens, $description]) {
            $command = implode(' ', $tokens);
            $padding = max(0, $maxWidth - \strlen($command));
            $output->write('  '.$this->formatExampleCommand($output, $tokens).str_repeat(' ', $padding + 2).$output->dim('# '.$description)."\n");
        }

        $output->write("\n");
    }

    private function formatUsage(Output $output): string
    {
        return $output->color('regex', Output::BLUE)
            .' '.$output->color('<command>', Output::YELLOW)
            .' '.$output->color('[options]', Output::CYAN)
            .' '.$output->color('<pattern>', Output::GREEN);
    }

    private function formatCommand(Output $output, string $command): string
    {
        return $output->color($command, Output::GREEN.Output::BOLD);
    }

    private function formatOption(Output $output, string $option): string
    {
        if (!$output->isAnsi()) {
            return $option;
        }

        $parts = preg_split('/(<[^>]+>)/', $option, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            return $option;
        }

        $formatted = '';
        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }

            if ($this->isPlaceholder($part)) {
                $formatted .= $output->color($part, Output::YELLOW.Output::BOLD);

                continue;
            }

            $formatted .= $output->color($part, Output::CYAN);
        }

        return $formatted;
    }

    /**
     * @param array<int, string> $tokens
     */
    private function formatExampleCommand(Output $output, array $tokens): string
    {
        $formatted = [];
        foreach ($tokens as $index => $token) {
            $formatted[] = $this->formatExampleToken($output, $token, $index);
        }

        return implode(' ', $formatted);
    }

    private function formatExampleToken(Output $output, string $token, int $index): string
    {
        if (0 === $index && 'regex' === $token) {
            return $output->color($token, Output::BLUE.Output::BOLD);
        }

        if (str_starts_with($token, '-')) {
            return $output->color($token, Output::CYAN);
        }

        if ($this->isPatternToken($token)) {
            return $output->color($token, Output::GREEN);
        }

        if (\in_array($token, ['parse', 'analyze', 'debug', 'diagram', 'highlight', 'validate', 'lint', 'self-update', 'help'], true)) {
            return $output->color($token, Output::YELLOW.Output::BOLD);
        }

        return $token;
    }

    private function isPlaceholder(string $value): bool
    {
        return str_starts_with($value, '<') && str_ends_with($value, '>');
    }

    private function isPatternToken(string $token): bool
    {
        if (str_starts_with($token, "'/") && str_ends_with($token, "/'")) {
            return true;
        }

        return str_starts_with($token, '/') && str_ends_with($token, '/');
    }
}
