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

/**
 * JSON output formatter for machine-readable output.
 */
final class JsonFormatter extends AbstractOutputFormatter
{
    /**
     * @var array<string, mixed>
     */
    private array $results = [];

    public function getName(): string
    {
        return 'json';
    }

    public function format(RegexLintReport $report): string
    {
        $this->results = $report->results;
        $data = [
            'summary' => $this->formatSummary($report->stats),
            'results' => [],
        ];

        $groupedResults = $this->groupResults($report->results);

        foreach ($groupedResults as $file => $results) {
            $fileData = [
                'file' => $file,
                'patterns' => [],
            ];

            foreach ($results as $result) {
                $patternData = [
                    'line' => (int) ($result['line'] ?? 0),
                    'pattern' => (string) ($result['pattern'] ?? ''),
                    'location' => $result['location'] ?? null,
                ];

                // Add issues
                $issues = $result['issues'] ?? [];
                if (!empty($issues)) {
                    $patternData['issues'] = array_map([$this, 'formatIssue'], $issues);
                }

                // Add optimizations
                $optimizations = $result['optimizations'] ?? [];
                if (!empty($optimizations) && $this->config->shouldShowOptimizations()) {
                    $patternData['optimizations'] = array_map([$this, 'formatOptimization'], $optimizations);
                }

                $fileData['patterns'][] = $patternData;
            }

            $data['results'][] = $fileData;
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Format the main JSON structure.
     *
     * @param array<string, int> $stats
     */
    private function formatSummary(array $stats): array
    {
        return [
            'summary' => [
                'errors' => $stats['errors'],
                'warnings' => $stats['warnings'],
                'optimizations' => $stats['optimizations'],
                'total_patterns' => count($this->results),
            ],
        ];
    }

    /**
     * Format issues for JSON output.
     *
     * @param array<string, mixed> $issue
     */
    private function formatIssue(array $issue): array
    {
        $formatted = [
            'type' => (string) $issue['type'],
            'message' => (string) $issue['message'],
            'line' => (int) ($issue['line'] ?? 0),
            'column' => $issue['column'] ?? null,
            'position' => $issue['position'] ?? null,
        ];

        if (isset($issue['issueId'])) {
            $formatted['issue_id'] = (string) $issue['issueId'];
        }

        // Include hints based on verbosity level
        $hint = $issue['hint'] ?? null;
        if (\is_string($hint) && '' !== $hint) {
            if (($issue['issueId'] ?? '') === 'regex.lint.redos') {
                $formatted['hint'] = $this->formatReDoSHint($hint);
            } else {
                $formatted['hint'] = $this->formatHint($hint);
            }
        }

        return $formatted;
    }

    /**
     * Format optimization for JSON output.
     *
     * @param array<string, mixed> $optimization
     */
    private function formatOptimization(array $optimization): array
    {
        $opt = $optimization['optimization'];

        $formatted = [
            'original' => (string) $opt->original,
            'optimized' => (string) $opt->optimized,
            'savings' => (int) ($optimization['savings'] ?? 0),
        ];

        if (isset($opt->description)) {
            $formatted['description'] = (string) $opt->description;
        }

        return $formatted;
    }
}