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

use RegexParser\Lint\Formatter\LinkFormatter;
use RegexParser\Lint\Formatter\OutputFormatterInterface;
use RegexParser\Lint\RegexAnalysisService;
use RegexParser\Lint\RegexLintReport;
use RegexParser\OptimizationResult;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Symfony-specific console output formatter.
 *
 * Renders the classic Nuno-style layout with Symfony console tags.
 *
 * @phpstan-import-type LintIssue from RegexLintReport
 * @phpstan-import-type OptimizationEntry from RegexLintReport
 * @phpstan-import-type LintResult from RegexLintReport
 * @phpstan-import-type LintStats from RegexLintReport
 */
final readonly class SymfonyConsoleFormatter implements OutputFormatterInterface
{
    private const ARROW_LABEL = "\u{21B3}";

    public function __construct(
        private RegexAnalysisService $analysis,
        private LinkFormatter $linkFormatter,
        private bool $decorated = true,
    ) {}

    public function format(RegexLintReport $report): string
    {
        $parts = [];

        if (!empty($report->results)) {
            $parts[] = $this->renderResults($report->results);
        }

        $parts[] = $this->renderSummary($report->stats);

        return implode('', $parts);
    }

    public function formatError(string $message): string
    {
        return $message;
    }

    /**
     * @phpstan-param array<LintResult> $results
     */
    private function renderResults(array $results): string
    {
        $parts = [];

        foreach ($results as $result) {
            $parts[] = $this->renderResultCard($result);
        }

        return implode('', $parts);
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function renderResultCard(array $result): string
    {
        /** @var array<LintIssue> $issues */
        $issues = $result['issues'] ?? [];
        /** @var array<OptimizationEntry> $optimizations */
        $optimizations = $result['optimizations'] ?? [];

        $output = '';
        $output .= $this->displayPatternContext($result);
        $output .= $this->displayIssues($issues);
        $output .= $this->displayOptimizations($optimizations);
        $output .= \PHP_EOL;

        return $output;
    }

    /**
     * @phpstan-param LintResult $result
     */
    private function displayPatternContext(array $result): string
    {
        $pattern = $this->extractPatternForResult($result);
        $line = (int) $result['line'];
        $file = (string) $result['file'];
        $location = $result['location'] ?? null;

        $hasLocation = \is_string($location) && '' !== $location;

        $relPath = $this->linkFormatter->getRelativePath($file);
        if (!str_starts_with($relPath, '/')) {
            $relPath = './'.$relPath;
        }
        $label = $relPath;
        if ($line > 0) {
            $label .= ':'.$line;
        }
        $linkedLabel = $this->linkFormatter->format($file, $line, $label, 1, $label);

        $output = '  <fg=cyan;options=bold>'.$linkedLabel.'</>'.\PHP_EOL;

        if (null !== $pattern && '' !== $pattern) {
            $highlighted = $this->safelyHighlightPattern($pattern);
            $output .= '      <fg=cyan;options=bold>→ </><fg=white>'.$highlighted.'</>'.\PHP_EOL;
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
        // Wrap with / / if no delimiter
        if (!preg_match('/^[^a-zA-Z0-9\\\\]/', $pattern)) {
            $pattern = '/'.$pattern.'/';
        }

        // Always escape control characters to prevent layout issues
        $escapedPattern = addcslashes($pattern, "\0..\37\177..\377");

        if (!$this->decorated) {
            return OutputFormatter::escape($escapedPattern);
        }

        try {
            // Try highlighting the escaped pattern, but if it contains escapes, skip highlighting
            if (str_contains($escapedPattern, '\\')) {
                return OutputFormatter::escape($escapedPattern);
            }

            $highlighted = $this->analysis->highlight($pattern);

            return OutputFormatter::escape($highlighted);
        } catch (\Throwable) {
            return OutputFormatter::escape($escapedPattern);
        }
    }

    /**
     * @phpstan-param array<LintIssue> $issues
     */
    private function displayIssues(array $issues): string
    {
        $parts = [];

        foreach ($issues as $issue) {
            $issueType = (string) ($issue['type'] ?? 'info');
            $badge = $this->getIssueBadge($issueType);
            $parts[] = $this->displaySingleIssue($badge, (string) ($issue['message'] ?? ''));

            $hint = $issue['hint'] ?? null;
            if ('error' !== $issueType && \is_string($hint) && '' !== $hint) {
                $parts[] = \sprintf(
                    '         <fg=gray>%s %s</>'.\PHP_EOL,
                    self::ARROW_LABEL,
                    OutputFormatter::escape($hint),
                );
            }
        }

        return implode('', $parts);
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
     * @phpstan-param array<OptimizationEntry> $optimizations
     */
    private function displayOptimizations(array $optimizations): string
    {
        $parts = [];

        foreach ($optimizations as $opt) {
            $parts[] = '    <bg=cyan;fg=white;options=bold> TIP </>'.\PHP_EOL;

            $optimization = $opt['optimization'] ?? null;
            if (!$optimization instanceof OptimizationResult) {
                continue;
            }

            $original = $this->safelyHighlightPattern($optimization->original);
            $optimized = $this->safelyHighlightPattern($optimization->optimized);

            $parts[] = \sprintf('         <fg=red>- %s</>'.\PHP_EOL, $original);
            $parts[] = \sprintf('         <fg=green>+ %s</>'.\PHP_EOL, $optimized);
        }

        return implode('', $parts);
    }

    private function displaySingleIssue(string $badge, string $message): string
    {
        $lines = explode("\n", $message);
        $firstLine = array_shift($lines) ?? '';

        $parts = [\sprintf(
            '    %s <fg=white>%s</>'.\PHP_EOL,
            $badge,
            OutputFormatter::escape($firstLine),
        )];

        if (!empty($lines)) {
            foreach ($lines as $index => $line) {
                $parts[] = \sprintf(
                    '         <fg=gray>%s %s</>'.\PHP_EOL,
                    0 === $index ? self::ARROW_LABEL : ' ',
                    OutputFormatter::escape($this->stripMessageLine($line)),
                );
            }
        }

        return implode('', $parts);
    }

    /**
     * @phpstan-param LintStats $stats
     */
    private function renderSummary(array $stats): string
    {
        $output = \PHP_EOL;
        $output .= $this->showSummaryMessage($stats);

        return $output;
    }

    /**
     * @phpstan-param LintStats $stats
     */
    private function showSummaryMessage(array $stats): string
    {
        $errors = (int) $stats['errors'];
        $warnings = (int) $stats['warnings'];
        $optimizations = (int) $stats['optimizations'];

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
