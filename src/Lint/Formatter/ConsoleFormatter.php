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

namespace RegexParser\Lint\Formatter;

use RegexParser\Internal\PatternParser;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintReport;
use RegexParser\OptimizationResult;

/**
 * Console output formatter with ANSI colors and verbosity levels.
 *
 * @phpstan-import-type LintIssue from \RegexParser\Lint\RegexLintReport
 * @phpstan-import-type OptimizationEntry from \RegexParser\Lint\RegexLintReport
 * @phpstan-import-type LintResult from \RegexParser\Lint\RegexLintReport
 * @phpstan-import-type LintStats from \RegexParser\Lint\RegexLintReport
 */
class ConsoleFormatter extends AbstractOutputFormatter
{
    // ANSI color codes
    private const RESET = "\033[0m";
    private const RED = "\033[31m";
    private const GREEN = "\033[32m";
    private const YELLOW = "\033[33m";
    private const BLACK = "\033[30m";
    private const CYAN = "\033[36m";
    private const WHITE = "\033[37m";
    private const GRAY = "\033[90m";
    private const BOLD = "\033[1m";
    private const BG_RED = "\033[41m";
    private const BG_GREEN = "\033[42m";
    private const BG_YELLOW = "\033[43m";
    private const BG_CYAN = "\033[46m";
    private const BG_GRAY = "\033[100m";
    private const ARROW_LABEL = '↳';

    public function __construct(
        private readonly ?RegexAnalysisService $analysisService = null,
        OutputConfiguration $config = new OutputConfiguration(),
    ) {
        parent::__construct($config);
    }

    public function format(RegexLintReport $report): string
    {
        if (OutputConfiguration::VERBOSITY_QUIET === $this->config->verbosity) {
            return $this->formatQuiet($report);
        }

        $output = '';
        $groupedResults = $this->groupResults($report->results);

        foreach ($groupedResults as $file => $results) {
            $file = (string) $file;
            if ('' !== $file) {
                $output .= $this->formatFileHeader($file);
            }

            /** @var list<LintResult> $results */
            foreach ($results as $result) {
                $output .= $this->formatPatternContext($result);
                /** @var list<LintIssue> $issues */
                $issues = $result['issues'] ?? [];
                $output .= $this->formatIssues($issues);
                /** @var list<OptimizationEntry> $optimizations */
                $optimizations = $result['optimizations'] ?? [];
                $output .= $this->formatOptimizations($optimizations);
                $output .= \PHP_EOL;
            }
        }

        $output .= $this->formatSummary($report->stats);
        $output .= $this->formatFooter();

        return $output;
    }

    private function formatQuiet(RegexLintReport $report): string
    {
        $errors = $report->stats['errors'];
        $warnings = $report->stats['warnings'];

        if ($errors > 0) {
            return \sprintf('FAIL: %d invalid patterns, %d warnings, %d optimizations.'.\PHP_EOL,
                $errors, $warnings, $report->stats['optimizations']);
        }

        if ($warnings > 0) {
            return \sprintf('WARN: %d warnings found, %d optimizations available.'.\PHP_EOL,
                $warnings, $report->stats['optimizations']);
        }

        return \sprintf('PASS: No issues found, %d optimizations available.'.\PHP_EOL,
            $report->stats['optimizations']);
    }

    /**
     * Format file header.
     */
    private function formatFileHeader(string $file): string
    {
        if (!$this->config->ansi) {
            return '  '.$file.\PHP_EOL;
        }

        return '  '.$this->color(' '.$file.' ', self::BG_GRAY.self::WHITE.self::BOLD).\PHP_EOL;
    }

    /**
     * Format pattern context information.
     *
     * @phpstan-param LintResult $result
     */
    private function formatPatternContext(array $result): string
    {
        $pattern = $this->extractPatternForResult($result);
        $line = (int) ($result['line'] ?? 0);
        $location = $result['location'] ?? null;

        $hasLocation = \is_string($location) && '' !== $location;
        $showLine = $line > 0 && !$hasLocation;

        if ($showLine) {
            if (null !== $pattern && '' !== $pattern) {
                $formatted = $this->formatPatternForDisplay($pattern);

                return \sprintf('  %s %s'.\PHP_EOL, $this->dim($line.':'), $formatted);
            }

            return \sprintf('  %s'.\PHP_EOL, $this->dim('line '.$line.':'));
        }

        if (null !== $pattern && '' !== $pattern) {
            $formatted = $this->formatPatternForDisplay($pattern);
            $output = \sprintf('  %s'.\PHP_EOL, $formatted);
        } else {
            $output = '  '.$this->dim('(pattern unavailable)').\PHP_EOL;
        }

        if ($hasLocation) {
            $output .= \sprintf('     %s'.\PHP_EOL, $this->dim(self::ARROW_LABEL.' '.$location));
        }

        return $output;
    }

    /**
     * Format issues for a result.
     *
     * @phpstan-param list<LintIssue> $issues
     */
    private function formatIssues(array $issues): string
    {
        $output = '';

        foreach ($issues as $issue) {
            $issueType = (string) ($issue['type'] ?? 'info');
            $badge = $this->issueBadge($issueType);
            $output .= $this->displaySingleIssue($badge, (string) ($issue['message'] ?? ''));

            $hint = $issue['hint'] ?? null;
            if ('error' !== $issueType && \is_string($hint) && '' !== $hint && $this->config->shouldShowHints()) {
                $formattedHint = $this->formatHint($hint);
                if ('' !== $formattedHint) {
                    $output .= \sprintf('         %s'.\PHP_EOL, $this->dim(self::ARROW_LABEL.' '.$formattedHint));
                }
            }
        }

        return $output;
    }

    /**
     * Format optimizations for a result.
     *
     * @phpstan-param list<OptimizationEntry> $optimizations
     */
    private function formatOptimizations(array $optimizations): string
    {
        if (!$this->config->shouldShowOptimizations() || empty($optimizations)) {
            return '';
        }

        $output = '';

        foreach ($optimizations as $opt) {
            $output .= \sprintf('    %s %s'.\PHP_EOL,
                $this->badge('TIP', self::WHITE, self::BG_CYAN),
                $this->color('Optimization available', self::CYAN.self::BOLD),
            );

            $optimization = $opt['optimization'] ?? null;
            if (!$optimization instanceof OptimizationResult) {
                continue;
            }

            $original = $optimization->original;
            $optimized = $optimization->optimized;

            $isExtendedWithComments = $this->isExtendedPatternWithComments($original);

            // Always show the original pattern as-is.
            $output .= \sprintf('         %s%s'.\PHP_EOL,
                $this->color('- ', self::RED),
                $original,
            );

            // For /x patterns with comments, format the optimized pattern
            // with proper indentation to make it more readable.
            if ($isExtendedWithComments) {
                $output .= $this->formatExtendedOptimizedPattern($optimized);

                continue;
            }

            // For other patterns, show raw original and optimized regex so
            // that textual changes (e.g. escaping inside character classes)
            // remain visible.
            $output .= \sprintf('         %s%s'.\PHP_EOL,
                $this->color('+ ', self::GREEN),
                $optimized,
            );
        }

        return $output;
    }

    /**
     * Format an optimized pattern from an extended (/x) pattern with comments.
     *
     * The optimized pattern is typically a single-line, heavily escaped string.
     * This method formats it with proper indentation to make it more readable.
     */
    private function formatExtendedOptimizedPattern(string $optimized): string
    {
        $output = '';

        // Show the optimized pattern with green + prefix
        // For multi-line patterns, indent each line properly
        $lines = explode("\n", $optimized);

        if (1 === \count($lines)) {
            // Single line - just show it directly
            $output .= \sprintf('         %s%s'.\PHP_EOL,
                $this->color('+ ', self::GREEN),
                $optimized,
            );
        } else {
            // Multi-line - show each line with proper indentation
            foreach ($lines as $index => $line) {
                if (0 === $index) {
                    $output .= \sprintf('         %s%s'.\PHP_EOL,
                        $this->color('+ ', self::GREEN),
                        $line,
                    );
                } else {
                    $output .= \sprintf('           %s'.\PHP_EOL, $line);
                }
            }
        }

        return $output;
    }

    /**
     * Heuristic to detect extended-mode patterns with inline comments.
     *
     * We inspect the original pattern string to see if it has /x flags and
     * contains at least one '\n' and '#' in the body; such patterns are
     * typically written in verbose style, and dumping the fully escaped
     * optimized variant is not helpful in console output.
     */
    private function isExtendedPatternWithComments(string $pattern): bool
    {
        $pattern = ltrim($pattern);
        if ('' === $pattern) {
            return false;
        }

        try {
            [$body, $flags] = PatternParser::extractPatternAndFlags($pattern);
        } catch (\Throwable) {
            return false;
        }

        if (!\is_string($flags) || !str_contains($flags, 'x')) {
            return false;
        }

        return \is_string($body) && str_contains($body, "\n") && str_contains($body, '#');
    }

    /**
     * Format summary statistics.
     *
     * @phpstan-param LintStats $stats
     */
    private function formatSummary(array $stats): string
    {
        $output = \PHP_EOL;

        $errors = (int) $stats['errors'];
        $warnings = (int) $stats['warnings'];
        $optimizations = (int) $stats['optimizations'];

        if ($errors > 0) {
            $output .= \sprintf('  %s %s%s'.\PHP_EOL,
                $this->badge('FAIL', self::WHITE, self::BG_RED),
                $this->color(\sprintf('%d invalid patterns', $errors), self::RED.self::BOLD),
                $this->dim(\sprintf(', %d warnings, %d optimizations.', $warnings, $optimizations)),
            );
        } elseif ($warnings > 0) {
            $output .= \sprintf('  %s %s%s'.\PHP_EOL,
                $this->badge('PASS', self::BLACK, self::BG_YELLOW),
                $this->color(\sprintf('%d warnings found', $warnings), self::YELLOW.self::BOLD),
                $this->dim(\sprintf(', %d optimizations available.', $optimizations)),
            );
        } else {
            $output .= \sprintf('  %s %s%s'.\PHP_EOL,
                $this->badge('PASS', self::WHITE, self::BG_GREEN),
                $this->color('No issues found', self::GREEN.self::BOLD),
                $this->dim(\sprintf(', %d optimizations available.', $optimizations)),
            );
        }

        return $output;
    }

    /**
     * Format footer.
     */
    private function formatFooter(): string
    {
        return \PHP_EOL
            .'  '.$this->dim('Star the repo: https://github.com/yoeunes/regex-parser')
            .\PHP_EOL
            .\PHP_EOL;
    }

    private function displaySingleIssue(string $badge, string $message): string
    {
        $lines = explode("\n", $message);
        $firstLine = array_shift($lines) ?? '';

        $output = \sprintf(
            '    %s %s'.\PHP_EOL,
            $badge,
            $this->color($firstLine, self::WHITE),
        );

        if (!empty($lines)) {
            foreach ($lines as $index => $line) {
                $prefix = 0 === $index ? self::ARROW_LABEL : ' ';
                $output .= \sprintf(
                    '         %s %s'.\PHP_EOL,
                    $this->dim($prefix),
                    $this->dim($this->stripMessageLine($line)),
                );
            }
        }

        return $output;
    }

    // getPenLabel() is intentionally omitted in this formatter. The plain
    // console output does not currently render a clickable "pen" link; this
    // responsibility is handled by the SymfonyConsoleFormatter bridge.

    private function safelyHighlightBody(string $body, string $flags, string $delimiter): ?string
    {
        if (!$this->analysisService || !$this->config->ansi) {
            return null;
        }

        try {
            return $this->analysisService->highlightBody($body, $flags, $delimiter);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Format pattern for display.
     *
     * The linter output must be text-preserving: the pattern shown in the
     * console should match exactly what was found in the source file.
     *
     * We try the AST-based highlighter first for better syntax highlighting,
     * falling back to a lightweight regex-based highlighter if parsing fails.
     * Both preserve the exact characters while adding ANSI color codes to
     * delimiters, flags, and pattern elements.
     */
    private function formatPatternForDisplay(string $pattern): string
    {
        // No ANSI: return the raw pattern exactly as we received it.
        if (!$this->config->ansi) {
            return $pattern;
        }

        // Try to split /body/flags or {body}flags so we can color the
        // delimiters and flags without touching the body itself.
        $parts = $this->splitPatternWithFlags($pattern);
        if (null === $parts) {
            // Unknown structure (e.g. missing delimiters) – fall back to
            // returning the raw pattern unchanged, just to be safe.
            return $pattern;
        }

        $delimiter = $parts['delimiter'];
        $closingDelimiter = $parts['closingDelimiter'];
        $flags = $parts['flags'];

        // Recompute the last closing-delimiter position so we can slice out
        // the body without interpreting escapes. This mirrors the behavior of
        // PatternParser but never alters the bytes.
        $lastPos = strrpos($pattern, $closingDelimiter);
        if (false === $lastPos || 0 === $lastPos) {
            return $pattern;
        }

        $body = substr($pattern, 1, $lastPos - 1);

        // Try the AST-based highlighter first for better highlighting, then
        // fall back to the lightweight, text-preserving highlighter if it fails.
        // Both never change characters in the pattern; they only wrap segments in
        // ANSI color codes.
        $highlightedBody = $this->safelyHighlightBody($body, $flags, $delimiter);
        if (null === $highlightedBody) {
            $highlightedBody = $this->highlightPatternBodyPreservingText($body);
        }

        $open = $this->color($delimiter, self::CYAN.self::BOLD);
        $close = $this->color($closingDelimiter, self::CYAN.self::BOLD);

        if ('' === $flags) {
            return $open.$highlightedBody.$close;
        }

        return $open.$highlightedBody.$close.$this->color($flags, self::CYAN);
    }

    /**
     * Very small regex highlighter that operates directly on the pattern body
     * without changing any of its characters.
     *
     * It understands only a subset of PCRE syntax (escapes, character
     * classes, grouping, anchors, and basic quantifiers), but that is enough
     * to give a pleasant visual layout while guaranteeing the printed text is
     * identical to the source.
     */
    private function highlightPatternBodyPreservingText(string $body): string
    {
        if (!$this->config->ansi || '' === $body) {
            return $body;
        }

        $len = \strlen($body);
        $out = '';
        $inClass = false;
        $escaped = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];

            if ($escaped) {
                // Render "\\X" as a single green escape sequence.
                $out .= $this->color('\\'.$ch, self::GREEN);
                $escaped = false;

                continue;
            }

            if ('\\' === $ch) {
                $escaped = true;

                continue;
            }

            if ($inClass) {
                if (']' === $ch) {
                    $inClass = false;
                    $out .= $this->color(']', self::CYAN);

                    continue;
                }

                // Inside a character class we don't try to be clever; just
                // echo characters as-is to avoid mis-highlighting.
                $out .= $ch;

                continue;
            }

            if ('[' === $ch) {
                $inClass = true;
                $out .= $this->color('[', self::CYAN);

                continue;
            }

            // Grouping and structural meta-chars.
            if ('(' === $ch || ')' === $ch || '|' === $ch || '.' === $ch) {
                $out .= $this->color($ch, self::CYAN);

                continue;
            }

            // Simple quantifiers.
            if ('+' === $ch || '*' === $ch || '?' === $ch) {
                $out .= $this->color($ch, self::YELLOW);

                continue;
            }

            // Bounded quantifier like {2} or {1,3}.
            if ('{' === $ch) {
                $j = $i + 1;
                while ($j < $len && ctype_digit($body[$j])) {
                    $j++;
                }

                $isQuant = false;
                if ($j < $len && '}' === $body[$j]) {
                    $isQuant = true;
                } elseif ($j < $len && ',' === $body[$j]) {
                    $j++;
                    while ($j < $len && ctype_digit($body[$j])) {
                        $j++;
                    }
                    if ($j < $len && '}' === $body[$j]) {
                        $isQuant = true;
                    }
                }

                if ($isQuant) {
                    $segment = substr($body, $i, $j - $i + 1);
                    $out .= $this->color($segment, self::YELLOW);
                    $i = $j;

                    continue;
                }

                $out .= '{';

                continue;
            }

            // Anchors.
            if ('^' === $ch || '$' === $ch) {
                $out .= $this->color($ch, self::CYAN);

                continue;
            }

            $out .= $ch;
        }

        if ($escaped) {
            // Trailing backslash with no following char; just output it.
            $out .= '\\';
        }

        return $out;
    }

    /**
     * Split a regex pattern of the form /body/flags or {body}flags into its components.
     *
     * @return array{delimiter: string, closingDelimiter: string, flags: string}|null
     */
    private function splitPatternWithFlags(string $pattern): ?array
    {
        if ('' === $pattern) {
            return null;
        }

        $delimiter = $pattern[0];

        // Delimiters in PCRE must be non-alphanumeric, non-backslash, non-whitespace.
        if (ctype_alnum($delimiter) || '\\' === $delimiter || ctype_space($delimiter)) {
            return null;
        }

        // PCRE supports paired delimiters like {^pattern$}i, (pattern)i, etc.
        $closingDelimiter = match ($delimiter) {
            '{' => '}',
            '(' => ')',
            '[' => ']',
            '<' => '>',
            default => $delimiter,
        };

        $lastDelimiterPos = strrpos($pattern, $closingDelimiter);
        if (false === $lastDelimiterPos || 0 === $lastDelimiterPos) {
            return null;
        }

        $flags = substr($pattern, $lastDelimiterPos + 1);

        return [
            'delimiter' => $delimiter,
            'closingDelimiter' => $closingDelimiter,
            'flags' => $flags,
        ];
    }

    /**
     * Get badge for issue type.
     */
    private function issueBadge(string $type): string
    {
        return match ($type) {
            'error' => $this->badge('FAIL', self::WHITE, self::BG_RED),
            'warning' => $this->badge('WARN', self::BLACK, self::BG_YELLOW),
            'info' => $this->badge('INFO', self::WHITE, self::BG_GRAY),
            default => $this->badge('INFO', self::WHITE, self::BG_GRAY),
        };
    }

    /**
     * Create a badge with colors.
     */
    private function badge(string $text, string $fg, string $bg): string
    {
        if (!$this->config->ansi) {
            return '['.$text.']';
        }

        return $this->color(' '.$text.' ', $bg.$fg.self::BOLD);
    }

    /**
     * Apply color to text.
     */
    private function color(string $text, string $color): string
    {
        if (!$this->config->ansi) {
            return $text;
        }

        return $color.$text.self::RESET;
    }

    /**
     * Apply dim color to text.
     */
    private function dim(string $text): string
    {
        return $this->color($text, self::GRAY);
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function extractPatternForResult(array $result): ?string
    {
        $pattern = $result['pattern'] ?? null;
        if (\is_string($pattern) && '' !== $pattern) {
            return $pattern;
        }

        if (!empty($result['issues'])) {
            $firstIssue = $result['issues'][0];
            $issuePattern = $firstIssue['pattern'] ?? $firstIssue['regex'] ?? null;
            if (\is_string($issuePattern) && '' !== $issuePattern) {
                return $issuePattern;
            }
        }

        if (!empty($result['optimizations'])) {
            $firstOpt = $result['optimizations'][0];
            $optimization = $firstOpt['optimization'] ?? null;
            if ($optimization instanceof OptimizationResult) {
                return $optimization->original;
            }
        }

        return null;
    }

    private function stripMessageLine(string $message): string
    {
        return preg_replace_callback(
            '/^Line \d+:/m',
            static fn (array $matches): string => str_repeat(' ', \strlen($matches[0])),
            $message,
        ) ?? $message;
    }
}
