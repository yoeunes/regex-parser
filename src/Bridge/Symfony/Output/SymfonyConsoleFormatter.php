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

namespace RegexParser\Bridge\Symfony\Output;

use RegexParser\Bridge\Symfony\Console\LinkFormatter;
use RegexParser\Lint\Formatter\OutputFormatterInterface;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintReport;
use RegexParser\OptimizationResult;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Symfony-specific console output formatter.
 *
 * Renders the classic Nuno-style layout with Symfony console tags.
 */
final readonly class SymfonyConsoleFormatter implements OutputFormatterInterface
{
    private const PEN_LABEL = "\u{270F}\u{FE0F}";
    private const ARROW_LABEL = "\u{21B3}";

    /**
     * @phpstan-type LintIssue array{
     *     type: string,
     *     message: string,
     *     file: string,
     *     line: int,
     *     column?: int,
     *     position?: int,
     *     issueId?: string,
     *     hint?: string|null,
     *     source?: string,
     *     pattern?: string,
     *     regex?: string,
     *     analysis?: \RegexParser\ReDoS\ReDoSAnalysis,
     *     validation?: \RegexParser\ValidationResult
     * }
     * @phpstan-type OptimizationEntry array{
     *     file: string,
     *     line: int,
     *     optimization: OptimizationResult,
     *     savings: int,
     *     source?: string
     * }
     * @phpstan-type LintResult array{
     *     file: string,
     *     line: int,
     *     source?: string|null,
     *     pattern: string|null,
     *     location?: string|null,
     *     issues: list<LintIssue>,
     *     optimizations: list<OptimizationEntry>
     * }
     * @phpstan-type LintStats array{errors: int, warnings: int, optimizations: int}
     */
    public function __construct(
        private RegexAnalysisService $analysis,
        private LinkFormatter $linkFormatter,
        private bool $decorated = true,
    ) {}

    public function format(RegexLintReport $report): string
    {
        $output = '';

        if (!empty($report->results)) {
            $output .= $this->renderResults($report->results);
        }

        $output .= $this->renderSummary($report->stats);

        return $output;
    }

    public function formatError(string $message): string
    {
        return $message;
    }

    /**
     * @phpstan-param list<LintResult> $results
     */
    private function renderResults(array $results): string
    {
        $output = '';
        $currentFile = null;

        foreach ($results as $result) {
            $file = $result['file'];
            if ($file !== $currentFile) {
                $currentFile = $file;
                $output .= $this->renderFileHeader($file);
            }

            $output .= $this->renderResultCard($result);
        }

        return $output;
    }

    private function renderFileHeader(string $file): string
    {
        $relPath = $this->linkFormatter->getRelativePath($file);

        return \sprintf(
            '  <fg=white;bg=gray;options=bold> %s </>'.\PHP_EOL,
            OutputFormatter::escape($relPath),
        );
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function renderResultCard(array $result): string
    {
        $output = '';
        $output .= $this->displayPatternContext($result);
        $output .= $this->displayIssues($result['issues'] ?? []);
        $output .= $this->displayOptimizations($result['optimizations'] ?? []);
        $output .= \PHP_EOL;

        return $output;
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function displayPatternContext(array $result): string
    {
        $pattern = $this->extractPatternForResult($result);
        $line = $result['line'];
        $file = $result['file'];
        $location = $result['location'] ?? null;

        $hasLocation = \is_string($location) && '' !== $location;
        $showLine = $line > 0 && !$hasLocation;

        if ($showLine) {
            $penLink = $this->linkFormatter->format($file, $line, $this->getPenLabel(), 1, self::PEN_LABEL);

            if (null !== $pattern && '' !== $pattern) {
                $highlighted = $this->safelyHighlightPattern($pattern);

                return \sprintf('  <fg=gray>%d:</> %s %s'.\PHP_EOL, $line, $penLink, $highlighted);
            }

            return \sprintf('  <fg=gray>%s:</> %s'.\PHP_EOL, 'line '.$line, $penLink);
        }

        if (null !== $pattern && '' !== $pattern) {
            $highlighted = $this->safelyHighlightPattern($pattern);
            $output = \sprintf('  %s'.\PHP_EOL, $highlighted);
        } else {
            $output = '  <fg=gray>(pattern unavailable)</>'.\PHP_EOL;
        }

        if ($hasLocation) {
            $output .= \sprintf(
                '     <fg=gray>%s %s</>'.\PHP_EOL,
                self::ARROW_LABEL,
                OutputFormatter::escape($location),
            );
        }

        return $output;
    }

    private function safelyHighlightPattern(string $pattern): string
    {
        if (!$this->decorated) {
            return OutputFormatter::escape($pattern);
        }

        try {
            $highlighted = $this->analysis->highlight($pattern);

            return OutputFormatter::escape($highlighted);
        } catch (\Throwable) {
            return OutputFormatter::escape($pattern);
        }
    }

    private function getPenLabel(): string
    {
        if (!$this->decorated) {
            return self::PEN_LABEL;
        }

        return "\033[24m".self::PEN_LABEL."\033[24m";
    }

    /**
     * @phpstan-param list<LintIssue> $issues
     */
    private function displayIssues(array $issues): string
    {
        $output = '';

        foreach ($issues as $issue) {
            $issueType = (string) ($issue['type'] ?? 'info');
            $badge = $this->getIssueBadge($issueType);
            $output .= $this->displaySingleIssue($badge, (string) ($issue['message'] ?? ''));

            $hint = $issue['hint'] ?? null;
            if ('error' !== $issueType && \is_string($hint) && '' !== $hint) {
                $output .= \sprintf(
                    '         <fg=gray>%s %s</>'.\PHP_EOL,
                    self::ARROW_LABEL,
                    OutputFormatter::escape($hint),
                );
            }
        }

        return $output;
    }

    private function getIssueBadge(string $type): string
    {
        return match ($type) {
            'error' => '<bg=red;fg=white;options=bold> FAIL </>',
            'warning' => '<bg=yellow;fg=black;options=bold> WARN </>',
            default => '<bg=gray;fg=white;options=bold> INFO </>',
        };
    }

    /**
     * @phpstan-param list<OptimizationEntry> $optimizations
     */
    private function displayOptimizations(array $optimizations): string
    {
        $output = '';

        foreach ($optimizations as $opt) {
            $output .= '    <bg=cyan;fg=white;options=bold> TIP </> <fg=cyan;options=bold>Optimization available</>'.\PHP_EOL;

            $optimization = $opt['optimization'] ?? null;
            if (!$optimization instanceof OptimizationResult) {
                continue;
            }

            $original = $this->safelyHighlightPattern($optimization->original);
            $optimized = $this->safelyHighlightPattern($optimization->optimized);

            $output .= \sprintf('         <fg=red>- %s</>'.\PHP_EOL, $original);
            $output .= \sprintf('         <fg=green>+ %s</>'.\PHP_EOL, $optimized);
        }

        return $output;
    }

    private function displaySingleIssue(string $badge, string $message): string
    {
        $lines = explode("\n", $message);
        $firstLine = array_shift($lines) ?? '';

        $output = \sprintf(
            '    %s <fg=white>%s</>'.\PHP_EOL,
            $badge,
            OutputFormatter::escape($firstLine),
        );

        if (!empty($lines)) {
            foreach ($lines as $index => $line) {
                $output .= \sprintf(
                    '         <fg=gray>%s %s</>'.\PHP_EOL,
                    0 === $index ? self::ARROW_LABEL : ' ',
                    OutputFormatter::escape($this->stripMessageLine($line)),
                );
            }
        }

        return $output;
    }

    /**
     * @phpstan-param LintStats $stats
     */
    private function renderSummary(array $stats): string
    {
        $output = \PHP_EOL;
        $output .= $this->showSummaryMessage($stats);
        $output .= $this->showFooter();

        return $output;
    }

    /**
     * @phpstan-param LintStats $stats
     */
    private function showSummaryMessage(array $stats): string
    {
        $errors = $stats['errors'];
        $warnings = $stats['warnings'];
        $optimizations = $stats['optimizations'];

        $message = match (true) {
            $errors > 0 => \sprintf(
                '  <bg=red;fg=white;options=bold> FAIL </> <fg=red;options=bold>%d invalid patterns</><fg=gray>, %d warnings, %d optimizations.</>',
                $errors,
                $warnings,
                $optimizations,
            ),
            $warnings > 0 => \sprintf(
                '  <bg=yellow;fg=black;options=bold> PASS </> <fg=yellow;options=bold>%d warnings found</><fg=gray>, %d optimizations available.</>',
                $warnings,
                $optimizations,
            ),
            default => \sprintf(
                '  <bg=green;fg=white;options=bold> PASS </> <fg=green;options=bold>No issues found</><fg=gray>, %d optimizations available.</>',
                $optimizations,
            ),
        };

        return $message.\PHP_EOL;
    }

    private function showFooter(): string
    {
        return \PHP_EOL
            .'  <fg=gray>Star the repo: https://github.com/yoeunes/regex-parser</>'
            .\PHP_EOL
            .\PHP_EOL;
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
