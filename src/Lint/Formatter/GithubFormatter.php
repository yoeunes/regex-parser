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

use RegexParser\Lint\RegexLintReport;
use RegexParser\RegexProblem;
use RegexParser\Severity;

/**
 * GitHub Actions output formatter.
 *
 * @phpstan-import-type LintResult from \RegexParser\Lint\RegexLintReport
 *
 * @phpstan-type FlattenedProblem array{
 *     file: string,
 *     line: int,
 *     source?: string|null,
 *     pattern?: string|null,
 *     location?: string|null,
 *     problem: RegexProblem
 * }
 */
final class GithubFormatter extends AbstractOutputFormatter
{
    public function format(RegexLintReport $report): string
    {
        $output = '';

        foreach ($this->flattenProblems($report->results) as $entry) {
            $output .= $this->formatGithubAnnotation($entry)."\n";
        }

        return $output;
    }

    public function formatError(string $message): string
    {
        return "::error::{$this->escapeGithubData($message)}";
    }

    /**
     * @phpstan-param list<LintResult> $results
     *
     * @phpstan-return list<FlattenedProblem>
     */
    private function flattenProblems(array $results): array
    {
        $flattened = [];

        foreach ($results as $result) {
            foreach ((array) ($result['problems'] ?? []) as $problem) {
                if (!$problem instanceof RegexProblem) {
                    continue;
                }

                $flattened[] = [
                    'file' => $result['file'],
                    'line' => $result['line'],
                    'source' => $result['source'] ?? null,
                    'pattern' => $result['pattern'] ?? null,
                    'location' => $result['location'] ?? null,
                    'problem' => $problem,
                ];
            }
        }

        return $flattened;
    }

    /**
     * @phpstan-param FlattenedProblem $entry
     */
    private function formatGithubAnnotation(array $entry): string
    {
        $problem = $entry['problem'];
        \assert($problem instanceof RegexProblem);

        $level = $this->mapAnnotationLevel($problem->severity);
        $file = $entry['file'];
        $line = $entry['line'];
        $column = $problem->position ?? 1;
        $title = $this->formatProblemTitle($problem);
        $message = $this->formatProblemMessage($problem, $entry);

        $properties = [];
        if ('' !== $file) {
            $properties[] = 'file='.$this->escapeGithubProperty($file);
            $properties[] = 'line='.$line;
            $properties[] = 'col='.$column;
        }

        if ('' !== $title) {
            $properties[] = 'title='.$this->escapeGithubProperty($title);
        }

        $suffix = [] === $properties ? '' : ' '.implode(',', $properties);

        return \sprintf('::%s%s::%s', $level, $suffix, $this->escapeGithubData($message));
    }

    private function mapAnnotationLevel(Severity $severity): string
    {
        return match ($severity) {
            Severity::Error => 'error',
            Severity::Warning => 'warning',
            Severity::Info => 'notice',
            Severity::Critical => 'error',
        };
    }

    private function formatProblemTitle(RegexProblem $problem): string
    {
        $title = ucfirst($problem->type->value);
        if (null !== $problem->code && '' !== $problem->code) {
            $title .= ' ('.$problem->code.')';
        }

        return $title;
    }

    /**
     * @phpstan-param FlattenedProblem $context
     */
    private function formatProblemMessage(RegexProblem $problem, array $context): string
    {
        $parts = [$problem->message];
        $location = $context['location'] ?? null;

        if (\is_string($location) && '' !== $location) {
            $parts[] = 'Location: '.$location;
        }

        if (null !== $problem->snippet && '' !== $problem->snippet) {
            $parts[] = $problem->snippet;
        }

        if (null !== $problem->suggestion && '' !== $problem->suggestion) {
            $parts[] = 'Suggestion: '.$problem->suggestion;
        }

        return implode("\n", $parts);
    }

    private function escapeGithubProperty(string $value): string
    {
        return str_replace(
            ['%', "\n", "\r"],
            ['%25', '%0A', '%0D'],
            $value,
        );
    }

    private function escapeGithubData(string $value): string
    {
        return str_replace(
            ["\n", "\r"],
            ['%0A', '%0D'],
            $value,
        );
    }
}
