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
    public function format(RegexLintReport $report): string
    {
        $data = [
            'stats' => $report->stats,
            'results' => $this->normalizeResults($report->results),
        ];

        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('Failed to encode JSON');
        }

        return $json;
    }

    public function formatError(string $message): string
    {
        return json_encode(['error' => $message], \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array<string, mixed>> $results
     *
     * @return array<array<string, mixed>>
     */
    private function normalizeResults(array $results): array
    {
        $normalized = [];

        foreach ($results as $result) {
            if (!\is_array($result)) {
                continue;
            }

            $entry = $result;
            unset($entry['problems']);
            $normalized[] = $entry;
        }

        return $normalized;
    }
}
