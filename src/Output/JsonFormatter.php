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
    public function getName(): string
    {
        return 'json';
    }

    public function format(RegexLintReport $report): string
    {
        $data = [
            'summary' => [
                'errors' => $report->stats['errors'],
                'warnings' => $report->stats['warnings'],
                'optimizations' => $report->stats['optimizations'],
                'total_patterns' => count($report->results),
            ],
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
                    'line' => $result['line'] ?? 0,
                    'pattern' => $result['pattern'] ?? '',
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

    private function formatIssue(array $issue): array
    {
        $formatted = [
            'type' => $issue['type'],
            'message' => $issue['message'],
            'line' => $issue['line'] ?? 0,
            'column' => $issue['column'] ?? null,
            'position' => $issue['position'] ?? null,
        ];

        if (isset($issue['issueId'])) {
            $formatted['issue_id'] = $issue['issueId'];
        }

        // Include hints based on verbosity level
        $hint = $issue['hint'] ?? null;
        if (is_string($hint) && $hint !== '') {
            if (($issue['issueId'] ?? '') === 'regex.lint.redos') {
                $formatted['hint'] = $this->formatReDoSHint($hint);
            } else {
                $formatted['hint'] = $this->formatHint($hint);
            }
        }

        // Include analysis data for ReDoS issues in verbose mode
        if ($this->config->shouldShowDetailedReDoS() && isset($issue['analysis'])) {
            $formatted['redos_analysis'] = [
                'severity' => $issue['analysis']->severity->value,
                'vulnerable_subpattern' => $issue['analysis']->vulnerableSubpattern,
                'recommendations' => $issue['analysis']->recommendations,
            ];
        }

        // Include validation data for parsing errors in verbose mode
        if ($this->config->shouldShowDetailedReDoS() && isset($issue['validation'])) {
            $formatted['validation'] = [
                'error' => $issue['validation']->error,
                'offset' => $issue['validation']->offset,
            ];
        }

        return $formatted;
    }

    private function formatOptimization(array $optimization): array
    {
        $opt = $optimization['optimization'];

        $formatted = [
            'original' => $opt->original,
            'optimized' => $opt->optimized,
            'savings' => $optimization['savings'] ?? 0,
        ];

        if (isset($opt->description)) {
            $formatted['description'] = $opt->description;
        }

        return $formatted;
    }
}