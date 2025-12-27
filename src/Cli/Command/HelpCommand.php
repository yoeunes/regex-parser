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
        $innerLines = $this->signatureInnerLines();
        $maxLength = 0;
        foreach ($innerLines as $line) {
            $maxLength = \max($maxLength, \strlen($line));
        }

        $lineStates = [];
        foreach ($innerLines as $lineIndex => $line) {
            $content = $line.\str_repeat(' ', $maxLength - \strlen($line));
            $positions = $this->visiblePositions($content);
            $lineStates[] = [
                'content' => $content,
                'positions' => $positions,
                'offset' => $lineIndex * 4,
            ];
        }

        $frameCount = 0;
        foreach ($lineStates as $state) {
            $frameCount = \max($frameCount, \count($state['positions']));
        }
        $frameCount = \max($frameCount, 18);

        $width = $this->getTerminalWidth();
        $border = '+'.\str_repeat('-', $maxLength + 2).'+';
        $lineCount = \count($innerLines) + 2;

        if (!$animate) {
            $this->writeArtLine($output, $border, $width, null, false);
            foreach ($lineStates as $state) {
                $line = '| '.$state['content'].' |';
                $this->writeArtLine($output, $line, $width, null, false);
            }
            $this->writeArtLine($output, $border, $width, null, false);
            $output->write("\n");

            return;
        }

        if ($output->isAnsi()) {
            $output->write("\033[?25l");
        }

        for ($frame = 0; $frame < $frameCount; $frame++) {
            if (0 !== $frame) {
                $output->write("\033[".$lineCount."A");
            }

            $this->writeArtLine($output, $border, $width, null, true);
            foreach ($lineStates as $state) {
                $highlightIndex = $this->resolveHighlightIndex($state, $frame);
                $line = '| '.$state['content'].' |';
                $this->writeArtLine($output, $line, $width, $highlightIndex, true);
            }
            $this->writeArtLine($output, $border, $width, null, true);

            \usleep(42000);
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
    private function signatureInnerLines(): array
    {
        return [
            '[R][E][G][E][X][P][A][R][S][E][R]',
            '/(?:lint|parse|analyze|learn)/',
            '/^  Treat regex as code  $/',
        ];
    }

    /**
     * @param array{content: string, positions: array<int, int>, offset: int} $state
     */
    private function resolveHighlightIndex(array $state, int $frame): ?int
    {
        $positions = $state['positions'];
        if ([] === $positions) {
            return null;
        }

        $offset = $state['offset'];
        $index = ($frame + $offset) % \count($positions);

        return $positions[$index] + 1;
    }

    /**
     * @return array<int, int>
     */
    private function visiblePositions(string $content): array
    {
        $positions = [];
        $length = \strlen($content);

        for ($i = 0; $i < $length; $i++) {
            if (' ' !== $content[$i]) {
                $positions[] = $i;
            }
        }

        return $positions;
    }

    private function writeArtLine(Output $output, string $line, int $width, ?int $highlightIndex, bool $animate): void
    {
        $centered = $this->centerLine($line, $width);
        $isBorder = $this->isBorderLine($line);
        $colored = $this->colorizeArtLine($output, $centered, $isBorder, $highlightIndex);
        $prefix = $animate && $output->isAnsi() ? "\r\033[2K" : '';
        $output->write($prefix.$colored."\n");
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

    private function colorizeArtLine(Output $output, string $line, bool $isBorder, ?int $highlightIndex): string
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
            .$this->colorizeArtContent($output, $inner, $highlightIndex)
            .$output->color($suffix, Output::CYAN);
    }

    private function colorizeArtContent(Output $output, string $content, ?int $highlightIndex): string
    {
        $result = '';
        $chars = \str_split($content);

        foreach ($chars as $index => $char) {
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
