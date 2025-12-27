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
        $this->renderHeader($output, $showVisuals);

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
        $lines = $this->buildSignatureLines();
        $width = $this->getTerminalWidth();

        foreach ($lines as $line) {
            $centered = $this->centerLine($line, $width);
            $colored = $this->colorizeArtLine($output, $centered, $this->isBorderLine($line));
            $output->write($colored."\n");

            if ($animate) {
                \usleep(42000);
                if (\function_exists('fflush')) {
                    fflush(\STDOUT);
                }
            }
        }

        $output->write("\n");
    }

    private function shouldAnimate(Output $output, bool $showVisuals): bool
    {
        if (!$showVisuals || !$output->isAnsi() || $output->isQuiet()) {
            return false;
        }

        if (false !== \getenv('CI')) {
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

    /**
     * @return array<int, string>
     */
    private function buildSignatureLines(): array
    {
        $innerLines = [
            '[R][E][G][E][X][P][A][R][S][E][R]',
            '/(?:lint|parse|analyze|learn)/',
            '/^  Treat regex as code  $/',
        ];

        $maxLength = 0;
        foreach ($innerLines as $line) {
            $maxLength = \max($maxLength, \strlen($line));
        }

        $border = '+'.\str_repeat('-', $maxLength + 2).'+';
        $lines = [$border];

        foreach ($innerLines as $line) {
            $padding = $maxLength - \strlen($line);
            $lines[] = '| '.$line.\str_repeat(' ', $padding).' |';
        }

        $lines[] = $border;

        return $lines;
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

    private function centerLine(string $line, int $width): string
    {
        $length = \strlen($line);
        if ($length >= $width) {
            return $line;
        }

        $leftPad = (int) \floor(($width - $length) / 2);

        return \str_repeat(' ', $leftPad).$line;
    }

    private function isBorderLine(string $line): bool
    {
        $trimmed = \ltrim($line);

        return '' !== $trimmed && $trimmed[0] === '+';
    }

    private function colorizeArtLine(Output $output, string $line, bool $isBorder): string
    {
        if (!$output->isAnsi()) {
            return $line;
        }

        if ($isBorder) {
            return $output->color($line, Output::CYAN);
        }

        $prefixPos = \strpos($line, '|');
        $suffixPos = \strrpos($line, '|');
        if (false === $prefixPos || false === $suffixPos || $prefixPos === $suffixPos) {
            return $output->color($line, Output::CYAN);
        }

        $prefix = \substr($line, 0, $prefixPos + 1);
        $suffix = \substr($line, $suffixPos);
        $inner = \substr($line, $prefixPos + 1, $suffixPos - $prefixPos - 1);

        return $output->color($prefix, Output::CYAN)
            .$this->colorizeArtContent($output, $inner)
            .$output->color($suffix, Output::CYAN);
    }

    private function colorizeArtContent(Output $output, string $content): string
    {
        $result = '';
        $chars = \str_split($content);

        foreach ($chars as $char) {
            $result .= $output->color($char, $this->colorForArtChar($char));
        }

        return $result;
    }

    private function colorForArtChar(string $char): string
    {
        return match ($char) {
            '[', ']', '(', ')', '|', '{', '}' => Output::MAGENTA,
            '/', '\\' => Output::GREEN,
            '^', '$', '?', ':', '*', '+' => Output::YELLOW,
            default => Output::WHITE,
        };
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
        if (0 === $index && 'regex' === $token) {
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
