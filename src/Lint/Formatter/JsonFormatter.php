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

/**
 * JSON output formatter for machine-readable output.
 */
final class JsonFormatter extends AbstractOutputFormatter
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $results = [];

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
                    'line' => $result['line'] ?? 0,
                    'pattern' => $result['pattern'] ?? '',
                    'location' => $result['location'] ?? null,
                ];

                // Add issues
                /** @var array<array<string, mixed>> $issues */
                $issues = (array) ($result['issues'] ?? []);
                if (!empty($issues)) {
                    $patternData['issues'] = array_map([$this, 'formatIssue'], $issues);
                }

                // Add optimizations
                /** @var array<array<string, mixed>> $optimizations */
                $optimizations = (array) ($result['optimizations'] ?? []);
                if (!empty($optimizations) && $this->config->shouldShowOptimizations()) {
                    $patternData['optimizations'] = array_map([$this, 'formatOptimization'], $optimizations);
                }

                $fileData['patterns'][] = $patternData;
            }

            $data['results'][] = $fileData;
        }

        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('Failed to encode JSON');
        }

        return $json;
    }

    public function formatError(string $message): string
    {
        $json = json_encode(['error' => $message], \JSON_THROW_ON_ERROR);

        return $json;
    }

    /**
     * Format the main JSON structure.
     *
     * @param array<string, int> $stats
     *
     * @return array<string, mixed>
     */
    private function formatSummary(array $stats): array
    {
        return [
            'summary' => [
                'errors' => $stats['errors'],
                'warnings' => $stats['warnings'],
                'optimizations' => $stats['optimizations'],
                'total_patterns' => \count($this->results),
            ],
        ];
    }

    /**
     * Format issues for JSON output.
     *
     * @param array<string, mixed> $issue
     *
     * @return array<string, mixed>
     */
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
            /* @phpstan-ignore cast.string */
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
     *
     * @return array<string, mixed>
     */
    private function formatOptimization(array $optimization): array
    {
        $opt = $optimization['optimization'];
        if (!$opt instanceof \RegexParser\OptimizationResult) {
            return [];
        }

        $formatted = [
            'original' => $opt->original,
            'optimized' => $opt->optimized,
            'savings' => $optimization['savings'] ?? 0,
        ];

        // OptimizationResult doesn't have description property
        // if (isset($opt->description)) {
        //     $formatted['description'] = (string) $opt->description;
        // }

        return $formatted;
    }
}
