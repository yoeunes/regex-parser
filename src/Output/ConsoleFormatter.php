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

namespace RegexParser\Output;

use RegexParser\Lint\RegexLintReport;
use RegexParser\Lint\RegexAnalysisService;

/**
 * Console output formatter with ANSI colors and verbosity levels.
 */
final class ConsoleFormatter extends AbstractOutputFormatter
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
        private ?RegexAnalysisService $analysisService = null,
        OutputConfiguration $config = new OutputConfiguration(),
    ) {
        parent::__construct($config);
    }

    public function getName(): string
    {
        return 'console';
    }

    public function format(RegexLintReport $report): string
    {
        $output = '';

        if ($this->config->verbosity === OutputConfiguration::VERBOSITY_QUIET) {
            return $this->formatQuiet($report);
        }

        $groupedResults = $this->groupResults($report->results);

        foreach ($groupedResults as $file => $results) {
            $output .= $this->formatFileHeader($file);

            foreach ($results as $result) {
                $output .= $this->formatPatternContext($result);
                $output .= $this->formatIssues($result['issues'] ?? []);
                $output .= $this->formatOptimizations($result['optimizations'] ?? []);
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
            return sprintf('FAIL: %d invalid patterns, %d warnings, %d optimizations.' . PHP_EOL,
                $errors, $warnings, $report->stats['optimizations']);
        }

        if ($warnings > 0) {
            return sprintf('WARN: %d warnings found, %d optimizations available.' . PHP_EOL,
                $warnings, $report->stats['optimizations']);
        }

        return sprintf('PASS: No issues found, %d optimizations available.' . PHP_EOL,
            $report->stats['optimizations']);
    }

    private function formatFileHeader(string $file): string
    {
        return sprintf('  %s%s%s' . PHP_EOL,
            $this->color($file, self::BLUE . self::BOLD),
            $this->config->ansi ? ' ' : '',
            $this->config->ansi ? '┈' : ''
        );
    }

    private function formatPatternContext(array $result): string
    {
        $line = $result['line'] ?? 0;
        $pattern = $result['pattern'] ?? '';
        $location = $result['location'] ?? '';

        $output = sprintf('%3d: %s%s' . PHP_EOL,
            $line,
            $this->badge('✏️', self::WHITE, self::BG_BLUE),
            $this->highlightPattern($pattern)
        );

        if ($location && $this->config->verbosity !== OutputConfiguration::VERBOSITY_QUIET) {
            $output .= sprintf('     %s' . PHP_EOL, $this->dim($location));
        }

        return $output;
    }

    private function formatIssues(array $issues): string
    {
        $output = '';

        foreach ($issues as $issue) {
            $badge = $this->issueBadge($issue['type']);
            $output .= sprintf('     %s %s' . PHP_EOL,
                $badge,
                $this->color($issue['message'], $this->getSeverityColor($issue['type']) . self::BOLD)
            );

            $hint = $this->formatIssueHint($issue);
            if ($hint) {
                $output .= sprintf('         %s%s' . PHP_EOL,
                    $this->config->ansi ? '↳ ' : '',
                    $this->dim($hint)
                );
            }
        }

        return $output;
    }

    private function formatIssueHint(array $issue): string
    {
        $hint = $issue['hint'] ?? null;
        if (!is_string($hint) || '' === $hint) {
            return '';
        }

        // Special handling for ReDoS hints
        if (($issue['issueId'] ?? '') === 'regex.lint.redos') {
            return $this->formatReDoSHint($hint);
        }

        return $this->formatHint($hint);
    }

    private function formatOptimizations(array $optimizations): string
    {
        if (!$this->config->shouldShowOptimizations() || empty($optimizations)) {
            return '';
        }

        $output = '';

        foreach ($optimizations as $opt) {
            $output .= sprintf('     %s %s' . PHP_EOL,
                $this->badge('TIP', self::WHITE, self::BG_CYAN),
                $this->color('Optimization available', self::CYAN . self::BOLD)
            );

            if ($this->analysisService) {
                $original = $this->safelyHighlightPattern($opt['optimization']->original);
                $optimized = $this->safelyHighlightPattern($opt['optimization']->optimized);

                $output .= sprintf('         %s%s' . PHP_EOL,
                    $this->color('- ', self::RED),
                    $original
                );
                $output .= sprintf('         %s%s' . PHP_EOL,
                    $this->color('+ ', self::GREEN),
                    $optimized
                );
            }
        }

        return $output;
    }

    private function formatSummary(array $stats): string
    {
        $output = PHP_EOL;

        $errors = $stats['errors'];
        $warnings = $stats['warnings'];
        $optimizations = $stats['optimizations'];

        if ($errors > 0) {
            $output .= sprintf('  %s %s%s' . PHP_EOL,
                $this->badge('FAIL', self::WHITE, self::BG_RED),
                $this->color(sprintf('%d invalid patterns', $errors), self::RED . self::BOLD),
                $this->dim(sprintf(', %d warnings, %d optimizations.', $warnings, $optimizations))
            );
        } elseif ($warnings > 0) {
            $output .= sprintf('  %s %s%s' . PHP_EOL,
                $this->badge('PASS', self::BLACK, self::BG_YELLOW),
                $this->color(sprintf('%d warnings found', $warnings), self::YELLOW . self::BOLD),
                $this->dim(sprintf(', %d optimizations available.', $optimizations))
            );
        } else {
            $output .= sprintf('  %s %s%s' . PHP_EOL,
                $this->badge('PASS', self::WHITE, self::BG_GREEN),
                $this->color('No issues found', self::GREEN . self::BOLD),
                $this->dim(sprintf(', %d optimizations available.', $optimizations))
            );
        }

        return $output;
    }

    private function formatFooter(): string
    {
        $output = PHP_EOL;

        if ($this->config->ansi) {
            $output .= $this->dim('  Star the repo: https://github.com/yoeunes/regex-parser') . PHP_EOL;
        }

        $output .= PHP_EOL;

        return $output;
    }

    private function highlightPattern(string $pattern): string
    {
        if (!$this->analysisService || !$this->config->ansi) {
            return $pattern;
        }

        return $this->safelyHighlightPattern($pattern);
    }

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

    private function badge(string $text, string $fg, string $bg): string
    {
        if (!$this->config->ansi) {
            return '['.$text.']';
        }

        return $this->color(' '.$text.' ', $bg . $fg . self::BOLD);
    }

    private function color(string $text, string $color): string
    {
        if (!$this->config->ansi) {
            return $text;
        }

        return $color . $text . self::RESET;
    }

    private function dim(string $text): string
    {
        return $this->color($text, self::GRAY);
    }

    private function issueBadge(string $type): string
    {
        return match ($type) {
            'error' => $this->badge('FAIL', self::WHITE, self::BG_RED),
            'warning' => $this->badge('WARN', self::BLACK, self::BG_YELLOW),
            'info' => $this->badge('INFO', self::WHITE, self::BG_BLUE),
            default => $this->badge('NOTE', self::BLACK, self::BG_GRAY),
        };
    }
}