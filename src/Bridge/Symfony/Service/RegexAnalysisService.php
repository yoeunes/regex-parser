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

namespace RegexParser\Bridge\Symfony\Service;

use RegexParser\Bridge\Symfony\Extractor\RegexPatternExtractor;
use RegexParser\Bridge\Symfony\Extractor\TokenBasedExtractionStrategy;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

/**
 * Handles regex-related analysis and transformations.
 */
final readonly class RegexAnalysisService
{
    public function __construct(
        private Regex $regex,
        private ?RegexPatternExtractor $extractor = null,
    ) {}

    /**
     * @param list<string> $paths
     * @param list<string> $excludePaths
     */
    public function scan(array $paths, array $excludePaths): array
    {
        $extractor = $this->extractor ?? new RegexPatternExtractor(
            new TokenBasedExtractionStrategy(),
        );

        return $extractor->extract($paths, $excludePaths);
    }

    /**
     * @param array<int, object> $patterns
     *
     * @return array<int, array{
     *     type: string,
     *     file: string,
     *     line: int,
     *     column: int,
     *     message: string,
     *     issueId?: string,
     *     hint?: string|null,
     *     source?: string
     * }>
     */
    public function lint(array $patterns, ?callable $progress = null): array
    {
        $issues = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            $source = $occurrence->source ?? '';
            if (!$validation->isValid) {
                $issues[] = [
                    'type' => 'error',
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'column' => 1,
                    'message' => $validation->error ?? 'Invalid regex.',
                    'source' => $source,
                ];

                if ($progress) {
                    $progress();
                }

                continue;
            }

            $ast = $this->regex->parse($occurrence->pattern);
            $linter = new LinterNodeVisitor();
            $ast->accept($linter);

            foreach ($linter->getIssues() as $issue) {
                $issues[] = [
                    'type' => 'warning',
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'column' => 1,
                    'issueId' => $issue->id,
                    'message' => $issue->message,
                    'hint' => $issue->hint,
                    'source' => $source,
                ];
            }

            if ($progress) {
                $progress();
            }
        }

        return $issues;
    }

    /**
     * @param array<int, object> $patterns
     *
     * @return array<int, array{file: string, line: int, analysis: object}>
     */
    public function analyzeRedos(array $patterns, ReDoSSeverity $threshold): array
    {
        $issues = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            if (!$validation->isValid) {
                continue;
            }

            $analysis = $this->regex->analyzeReDoS($occurrence->pattern);
            if (!$analysis->exceedsThreshold($threshold)) {
                continue;
            }

            $issues[] = [
                'file' => $occurrence->file,
                'line' => $occurrence->line,
                'analysis' => $analysis,
            ];
        }

        return $issues;
    }

    /**
     * @param array<int, object> $patterns
     *
     * @return array<int, array{
     *     file: string,
     *     line: int,
     *     optimization: object,
     *     savings: int,
     *     source?: string
     * }>
     */
    public function suggestOptimizations(array $patterns, int $minSavings): array
    {
        $suggestions = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            $source = $occurrence->source ?? '';
            if (!$validation->isValid) {
                continue;
            }

            try {
                $optimization = $this->regex->optimize($occurrence->pattern);
            } catch (\Throwable) {
                continue;
            }

            if (!$optimization->isChanged()) {
                continue;
            }

            $savings = \strlen($optimization->original) - \strlen($optimization->optimized);
            if ($savings < $minSavings) {
                continue;
            }

            $suggestions[] = [
                'file' => $occurrence->file,
                'line' => $occurrence->line,
                'optimization' => $optimization,
                'savings' => $savings,
                'source' => $source,
            ];
        }

        return $suggestions;
    }

    public function highlight(string $pattern): string
    {
        return $this->regex->highlightCli($pattern);
    }
}
