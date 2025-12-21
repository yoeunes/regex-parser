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



/**
 * Base class for output formatters.
 */
abstract class AbstractOutputFormatter implements OutputFormatterInterface
{
    public function __construct(
        protected OutputConfiguration $config = new OutputConfiguration(),
    ) {}

    public function supports(string $format): bool
    {
        return $format === $this->getName();
    }

    /**
     * Get the severity badge for an issue.
     */
    protected function getSeverityBadge(string $type): string
    {
        return match ($type) {
            'error' => 'FAIL',
            'warning' => 'WARN',
            'info' => 'INFO',
            default => 'NOTE',
        };
    }

    /**
     * Get the color for a severity level.
     */
    protected function getSeverityColor(string $type): string
    {
        return match ($type) {
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Format a hint based on verbosity level.
     */
    protected function formatHint(string $hint): string
    {
        if (!$this->config->shouldShowHints()) {
            return '';
        }

        // In verbose mode, show full hints
        if ($this->config->shouldShowDetailedReDoS()) {
            return $hint;
        }

        // In normal mode, truncate very long hints
        if (strlen($hint) > 200) {
            return substr($hint, 0, 197).'...';
        }

        return $hint;
    }

    /**
     * Format ReDoS hint based on verbosity.
     */
    protected function formatReDoSHint(string $hint): string
    {
        if (!$this->config->shouldShowHints()) {
            return '';
        }

        // In normal mode, provide a concise ReDoS hint
        if (!$this->config->shouldShowDetailedReDoS()) {
            return 'Nested quantifiers detected. Consider using atomic groups (?>...) or possessive quantifiers (*+, ++).';
        }

        return $hint;
    }

    /**
     * Group results by file if configured.
     */
    protected function groupResults(array $results): array
    {
        if (!$this->config->groupByFile) {
            return $results;
        }

        $grouped = [];
        foreach ($results as $result) {
            $file = $result['file'] ?? 'unknown';
            $grouped[$file][] = $result;
        }

        return $grouped;
    }

    /**
     * Sort results by severity if configured.
     */
    protected function sortResults(array $results): array
    {
        if (!$this->config->sortBySeverity) {
            return $results;
        }

        $severityOrder = ['error' => 0, 'warning' => 1, 'info' => 2];

        usort($results, function ($a, $b) use ($severityOrder) {
            $aSeverity = $a['type'] ?? 'info';
            $bSeverity = $b['type'] ?? 'info';

            return $severityOrder[$aSeverity] - $severityOrder[$bSeverity];
        });

        return $results;
    }
}