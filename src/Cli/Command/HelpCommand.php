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
        $showVisuals = $input->globalOptions->visuals;
        $binary = $this->resolveInvocation();
        $this->renderHeader($output, $showVisuals);

        $this->renderTextSection($output, 'Description', [
            'CLI for regex parsing, validation, analysis, and linting',
        ]);

        $this->renderTextSection($output, 'Usage', [
            $this->formatUsage($output, $binary),
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
            ['--no-visuals', 'Disable animated art in help output'],
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
            [[$binary, "'/a+/'"], 'Quick highlight'],
            [[$binary, 'parse', "'/a+/'", '--validate'], 'Parse with validation'],
            [[$binary, 'analyze', "'/a+/'"], 'Full analysis'],
            [[$binary, 'diagram', "'/^a+$/'"], 'ASCII diagram'],
            [[$binary, 'debug', "'/(a+)+$/'"], 'Heatmap + ReDoS details'],
            [[$binary, 'highlight', "'/a+/'", '--format=html'], 'HTML highlight'],
            [[$binary, 'lint', 'src/', '--exclude=vendor'], 'Lint a codebase'],
            [[$binary, 'lint', '--format=json', 'src/'], 'JSON output'],
            [[$binary, 'lint', '--verbose', 'src/'], 'Verbose output'],
            [[$binary, 'self-update'], 'Update the installed phar'],
        ];
        $this->renderExamplesSection($output, $examples);

        return 0;
    }

    private function renderHeader(Output $output, bool $showVisuals): void
    {
        $version = $this->versionResolver->resolve('dev') ?? 'dev';
        $output->write($this->formatHeaderLine($output, $this->renderStaticName($output), $version)."\n");

        if ($showVisuals) {
            $this->renderSignatureArt($output, $this->shouldAnimate($output, $showVisuals));
        }

        $output->write($output->dim('Treat Regular Expressions as Code.')."\n\n");
    }

    private function renderSignatureArt(Output $output, bool $animate): void
    {
        $lines = $this->signatureLines();
        $maxLength = 0;
        foreach ($lines as $line) {
            $maxLength = \max($maxLength, $this->stringLength($line));
        }

        $contentLines = [];
        foreach ($lines as $line) {
            $contentLines[] = $this->padLine($line, $maxLength);
        }

        $width = $this->getTerminalWidth();
        $lineCount = \count($contentLines);

        if (!$animate) {
            foreach ($contentLines as $content) {
                $this->writeArtLine($output, $content, $width, null, false);
            }
            $output->write("\n");

            return;
        }

        if ($output->isAnsi()) {
            $output->write("\033[?25l");
        }

        $frameCount = \min(16, \max(8, (int) \ceil($maxLength / 6)));
        for ($frame = 0; $frame < $frameCount; $frame++) {
            if (0 !== $frame) {
                $output->write("\033[".$lineCount."A");
            }

            $progress = $frameCount > 1 ? $frame / ($frameCount - 1) : 0.0;
            $highlightIndex = (int) \round($progress * \max(0, $maxLength - 1));
            foreach ($contentLines as $content) {
                $this->writeArtLine($output, $content, $width, $highlightIndex, true);
            }

            \usleep(18000);
            if (\function_exists('fflush')) {
                fflush(\STDOUT);
            }
        }

        if ($output->isAnsi()) {
            $output->write("\033[?25h");
        }

        $output->write("\n");
    }

    private function shouldAnimate(Output $output, bool $showVisuals): bool
    {
        if (!$showVisuals || !$output->isAnsi() || $output->isQuiet()) {
            return false;
        }

        if (!\function_exists('posix_isatty') || !posix_isatty(\STDOUT)) {
            return false;
        }

        if (false !== \getenv('CI')) {
            return false;
        }

        return true;
    }

    private function formatHeaderLine(Output $output, string $name, string $version): string
    {
        return $name.' '.$output->warning($version).' by Younes ENNAJI';
    }

    private function renderStaticName(Output $output): string
    {
        return $output->color('RegexParser', Output::CYAN.Output::BOLD);
    }

    /**
     * @return array<int, string>
     */
    private function signatureLines(): array
    {
        return [
            '█████╗ ███████╗ ██████╗ ███████╗██╗  ██╗██████╗  █████╗ ██████╗ ███████╗███████╗██████╗ ',
            '██╔══██╗██╔════╝██╔════╝ ██╔════╝╚██╗██╔╝██╔══██╗██╔══██╗██╔══██╗██╔════╝██╔════╝██╔══██╗',
            '██████╔╝█████╗  ██║  ███╗█████╗   ╚███╔╝ ██████╔╝███████║██████╔╝███████╗█████╗  ██████╔╝',
            '██╔══██╗██╔══╝  ██║   ██║██╔══╝   ██╔██╗ ██╔═══╝ ██╔══██║██╔══██╗╚════██║██╔══╝  ██╔══██╗',
            '██║  ██║███████╗╚██████╔╝███████╗██╔╝ ██╗██║     ██║  ██║██║  ██║███████║███████╗██║  ██║',
            '╚═╝  ╚═╝╚══════╝ ╚═════╝ ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝',
        ];
    }

    private function writeArtLine(Output $output, string $line, int $width, ?int $highlightIndex, bool $animate): void
    {
        $leftPad = $this->centerPadding($line, $width);
        $colored = $this->colorizeArtContent($output, $line, $highlightIndex);
        $prefix = $animate && $output->isAnsi() ? "\r\033[2K" : '';
        $output->write($prefix.\str_repeat(' ', $leftPad).$colored."\n");
    }

    private function getTerminalWidth(): int
    {
        $columns = \getenv('COLUMNS');
        if (\is_string($columns) && \ctype_digit($columns)) {
            $width = (int) $columns;
            if ($width >= 40) {
                return $width;
            }
        }

        return 80;
    }

    private function centerPadding(string $line, int $width): int
    {
        $length = $this->stringLength($line);
        if ($length >= $width) {
            return 0;
        }

        return (int) \floor(($width - $length) / 2);
    }

    private function colorizeArtContent(Output $output, string $content, ?int $highlightIndex): string
    {
        if (!$output->isAnsi()) {
            return $content;
        }

        $result = '';
        $chars = $this->splitChars($content);

        foreach ($chars as $index => $char) {
            if (' ' === $char) {
                $result .= $char;

                continue;
            }
            $result .= $output->color($char, $this->colorForArtChar($char, $index, $highlightIndex));
        }

        return $result;
    }

    private function colorForArtChar(string $char, int $index, ?int $highlightIndex): string
    {
        if (null !== $highlightIndex) {
            $distance = \abs($index - $highlightIndex);
            if (0 === $distance) {
                return Output::GREEN.Output::BOLD;
            }
            if (1 === $distance) {
                return Output::CYAN.Output::BOLD;
            }
            if (2 === $distance) {
                return Output::CYAN;
            }
        }

        return match ($char) {
            '█', '╔', '╗', '╚', '╝', '═' => Output::CYAN,
            '[', ']', '(', ')', '|', '{', '}' => Output::MAGENTA,
            '/', '\\' => Output::GREEN,
            '^', '$', '?', ':', '*', '+' => Output::YELLOW,
            default => Output::WHITE,
        };
    }

    private function stringLength(string $value): int
    {
        if (\function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return \count($this->splitChars($value));
    }

    /**
     * @return array<int, string>
     */
    private function splitChars(string $value): array
    {
        $chars = \preg_split('//u', $value, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $chars) {
            return \str_split($value);
        }

        return $chars;
    }

    private function padLine(string $line, int $length): string
    {
        $currentLength = $this->stringLength($line);
        if ($currentLength >= $length) {
            return $line;
        }

        return $line.\str_repeat(' ', $length - $currentLength);
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
            $maxWidth = \max($maxWidth, \strlen($row[0]));
        }

        foreach ($rows as [$left, $right]) {
            $padding = \max(0, $maxWidth - \strlen($left));
            $output->write('  '.$formatLeft($left).\str_repeat(' ', $padding + 2).$right."\n");
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
            $command = \implode(' ', $tokens);
            $maxWidth = \max($maxWidth, \strlen($command));
        }

        foreach ($examples as [$tokens, $description]) {
            $command = \implode(' ', $tokens);
            $padding = \max(0, $maxWidth - \strlen($command));
            $output->write('  '.$this->formatExampleCommand($output, $tokens).\str_repeat(' ', $padding + 2).$output->dim('# '.$description)."\n");
        }

        $output->write("\n");
    }

    private function resolveInvocation(): string
    {
        $argv = $_SERVER['argv'] ?? null;
        if (\is_array($argv) && isset($argv[0]) && \is_string($argv[0]) && '' !== $argv[0]) {
            return $argv[0];
        }

        return 'regex';
    }

    private function formatUsage(Output $output, string $binary): string
    {
        return $output->color($binary, Output::BLUE)
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

        $parts = \preg_split('/(<[^>]+>)/', $option, -1, \PREG_SPLIT_DELIM_CAPTURE);
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

        return \implode(' ', $formatted);
    }

    private function formatExampleToken(Output $output, string $token, int $index): string
    {
        if (0 === $index) {
            return $output->color($token, Output::BLUE.Output::BOLD);
        }

        if (\str_starts_with($token, '-')) {
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
        return \str_starts_with($value, '<') && \str_ends_with($value, '>');
    }

    private function isPatternToken(string $token): bool
    {
        if (\str_starts_with($token, "'/") && \str_ends_with($token, "/'")) {
            return true;
        }

        return \str_starts_with($token, '/') && \str_ends_with($token, '/');
    }
}
