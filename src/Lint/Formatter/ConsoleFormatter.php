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

use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintReport;

/**
 * Console output formatter with ANSI colors and verbosity levels.
 */
class ConsoleFormatter extends AbstractOutputFormatter
{
    // ANSI color codes
    private const RESET = "\033[0m";
    private const RED = "\033[31m";
    private const GREEN = "\033[32m";
    private const YELLOW = "\033[33m";
    private const BLUE = "\033[34m";
    private const BLACK = "\033[30m";
    private const CYAN = "\033[36m";
    private const WHITE = "\033[37m";
    private const GRAY = "\033[90m";
    private const BOLD = "\033[1m";
    private const BG_RED = "\033[41m";
    private const BG_GREEN = "\033[42m";
    private const BG_YELLOW = "\033[43m";
    private const BG_CYAN = "\033[46m";
    private const BG_BLUE = "\033[44m";
    private const BG_GRAY = "\033[100m";

    public function __construct(
        private readonly ?RegexAnalysisService $analysisService = null,
        OutputConfiguration $config = new OutputConfiguration(),
    ) {
        parent::__construct($config);
    }

    public function format(RegexLintReport $report): string
    {
        $output = '';

        if (OutputConfiguration::VERBOSITY_QUIET === $this->config->verbosity) {
            return $this->formatQuiet($report);
        }

        $groupedResults = $this->groupResults($report->results);

        foreach ($groupedResults as $file => $results) {
            $output .= $this->formatFileHeader($file);

            foreach ($results as $result) {
                $output .= $this->formatPatternContext($result);
                /** @var array<array<string, mixed>> $issues */
                $issues = (array) ($result['issues'] ?? []);
                $output .= $this->formatIssues($issues);
                /** @var array<array<string, mixed>> $optimizations */
                $optimizations = (array) ($result['optimizations'] ?? []);
                $output .= $this->formatOptimizations($optimizations);
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
        return \sprintf('  %s%s%s'.\PHP_EOL,
            $this->color($file, self::BLUE.self::BOLD),
            $this->config->ansi ? ' ' : '',
            $this->config->ansi ? '┈' : '',
        );
    }

    /**
     * Format pattern context information.
     *
     * @param array<string, mixed> $result
     */
    private function formatPatternContext(array $result): string
    {
        $line = $result['line'] ?? 0;
        $pattern = $result['pattern'] ?? '';
        $location = $result['location'] ?? '';

        $output = \sprintf('%3d: %s%s'.\PHP_EOL,
            /* @phpstan-ignore cast.int */ (int) $line,
            $this->badge('✏️', self::WHITE, self::BG_BLUE),
            $this->highlightPattern(/* @phpstan-ignore cast.string */ (string) $pattern),
        );

        if ($location && OutputConfiguration::VERBOSITY_QUIET !== $this->config->verbosity) {
            $output .= \sprintf('     %s'.\PHP_EOL, $this->dim(/* @phpstan-ignore cast.string */ (string) $location));
        }

        return $output;
    }

    /**
     * Format issues for a result.
     *
     * @param array<array<string, mixed>> $issues
     */
    private function formatIssues(array $issues): string
    {
        $output = '';

        foreach ($issues as $issue) {
            $badge = $this->issueBadge(/* @phpstan-ignore cast.string */ (string) $issue['type']);
            $cleanMessage = $this->cleanErrorMessage(/* @phpstan-ignore cast.string */ (string) $issue['message']);
            $output .= \sprintf('     %s %s'.\PHP_EOL,
                $badge,
                $this->color($cleanMessage, $this->getSeverityColor(/* @phpstan-ignore cast.string */ (string) $issue['type']).self::BOLD),
            );

            $hint = $this->formatIssueHint($issue);
            if ($hint) {
                $output .= \sprintf('         %s%s'.\PHP_EOL,
                    $this->config->ansi ? '↳ ' : '',
                    $this->dim($hint),
                );
            }
        }

        return $output;
    }

    /**
     * Format optimizations for a result.
     *
     * @param array<array<string, mixed>> $optimizations
     */
    private function formatOptimizations(array $optimizations): string
    {
        if (!$this->config->shouldShowOptimizations() || empty($optimizations)) {
            return '';
        }

        $output = '';

        foreach ($optimizations as $opt) {
            $output .= \sprintf('     %s %s'.\PHP_EOL,
                $this->badge('TIP', self::WHITE, self::BG_CYAN),
                $this->color('Optimization available', self::CYAN.self::BOLD),
            );

            if ($this->analysisService) {
                $optimization = $opt['optimization'];
                if ($optimization instanceof \RegexParser\OptimizationResult) {
                    $original = $this->safelyHighlightPattern($optimization->original);
                    $optimized = $this->safelyHighlightPattern($optimization->optimized);

                    $output .= \sprintf('         %s%s'.\PHP_EOL,
                        $this->color('- ', self::RED),
                        $original,
                    );
                    $output .= \sprintf('         %s%s'.\PHP_EOL,
                        $this->color('+ ', self::GREEN),
                        $optimized,
                    );
                }
            }
        }

        return $output;
    }

    /**
     * Format summary statistics.
     *
     * @param array<string, int> $stats
     */
    private function formatSummary(array $stats): string
    {
        $output = \PHP_EOL;

        $errors = $stats['errors'];
        $warnings = $stats['warnings'];
        $optimizations = $stats['optimizations'];

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
        $output = \PHP_EOL;

        if ($this->config->ansi) {
            $output .= $this->dim('  Star this repo: https://github.com/yoeunes/regex-parser').\PHP_EOL;
        }

        $output .= \PHP_EOL;

        return $output;
    }

    /**
     * Highlight a regex pattern.
     */
    private function highlightPattern(string $pattern): string
    {
        if (!$this->analysisService || !$this->config->ansi) {
            return $pattern;
        }

        try {
            return $this->analysisService->highlight($pattern);
        } catch (\Throwable) {
            return $pattern;
        }
    }

    /**
     * Safely highlight a regex pattern.
     */
    private function safelyHighlightPattern(string $pattern): string
    {
        if (!$this->analysisService) {
            return $pattern;
        }

        try {
            return $this->analysisService->highlight($pattern);
        } catch (\Throwable) {
            return $pattern;
        }
    }

    /**
     * Get badge for issue type.
     */
    private function issueBadge(string $type): string
    {
        return match ($type) {
            'error' => $this->badge('FAIL', self::WHITE, self::BG_RED),
            'warning' => $this->badge('WARN', self::BLACK, self::BG_YELLOW),
            'info' => $this->badge('INFO', self::WHITE, self::BG_BLUE),
            default => $this->badge('NOTE', self::BLACK, self::BG_GRAY),
        };
    }

    /**
     * Clean up validation error messages for normal mode.
     */
    private function cleanErrorMessage(string $message): string
    {
        // For normal mode, extract just the core error without tips
        if (OutputConfiguration::VERBOSITY_NORMAL === $this->config->verbosity) {
            // Extract the main error before the period
            $parts = explode('.', $message, 2);
            $mainError = $parts[0].'.';

            // Clean up common patterns
            $cleanups = [
                'No closing delimiter "/" found. You opened with "/"' => 'No closing delimiter "/" found.',
                'No closing delimiter "/" found' => 'No closing delimiter "/" found.',
                'Unclosed character class "]"' => 'Unclosed character class.',
                'Invalid quantifier range' => 'Invalid quantifier range.',
                'Backreference to non-existent group' => 'Backreference to non-existent group.',
                'Lookbehind is unbounded' => 'Unbounded lookbehind.',
                'Unknown regex flag' => 'Unknown regex flag(s) found.',
                'Invalid conditional construct' => 'Invalid conditional construct.',
            ];

            foreach ($cleanups as $verbose => $clean) {
                $mainError = str_replace($verbose, $clean, $mainError);
            }

            return $mainError;
        }

        return $message;
    }

    /**
     * Format issue hint.
     *
     * @param array<string, mixed> $issue
     */
    private function formatIssueHint(array $issue): string
    {
        $hint = $issue['hint'] ?? null;
        if (!\is_string($hint) || '' === $hint) {
            return '';
        }

        // Special handling for ReDoS hints
        if (($issue['issueId'] ?? '') === 'regex.lint.redos') {
            return $this->formatReDoSHint($hint);
        }

        return $this->formatHint($hint);
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
}
