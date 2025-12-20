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

namespace RegexParser\Lint;

use RegexParser\OptimizationResult;

/**
 * Aggregates pattern sources and produces lint results.
 *
 * @internal
 *
 * @phpstan-type LintIssue array{
 *     type: string,
 *     message: string,
 *     file: string,
 *     line: int,
 *     column?: int,
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
final readonly class RegexLintService
{
    private const ROUTE_IGNORED_ISSUE_IDS = [
        'regex.lint.quantifier.nested' => true,
        'regex.lint.dotstar.nested' => true,
    ];

    public function __construct(
        private RegexAnalysisService $analysis,
        private RegexPatternSourceCollection $sources,
    ) {}

    /**
     * @return list<RegexPatternOccurrence>
     */
    public function collectPatterns(RegexLintRequest $request): array
    {
        $context = new RegexPatternSourceContext(
            $request->paths,
            $request->excludePaths,
            $request->getDisabledSources(),
        );

        return $this->sources->collect($context);
    }

    /**
     * @param list<RegexPatternOccurrence> $patterns
     */
    public function analyze(array $patterns, RegexLintRequest $request, ?callable $progress = null): RegexLintReport
    {
        $issues = $this->analysis->lint($patterns, $progress);
        $issues = $this->filterLintIssues($issues);

        $optimizations = $this->analysis->suggestOptimizations($patterns, $request->minSavings);

        $results = $this->combineResults($issues, $optimizations, $patterns);

        $stats = $this->updateStatsFromResults($this->createStats(), $results);

        return new RegexLintReport($results, $stats);
    }

    /**
     * @return LintStats
     */
    private function createStats(): array
    {
        return ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];
    }

    /**
     * @phpstan-param list<LintIssue> $issues
     *
     * @phpstan-return list<LintIssue>
     */
    private function filterLintIssues(array $issues): array
    {
        return array_values(array_filter($issues, static function (array $issue): bool {
            $source = $issue['source'] ?? '';

            if (str_starts_with($source, 'route:')) {
                $issueId = $issue['issueId'] ?? null;
                if (\is_string($issueId) && isset(self::ROUTE_IGNORED_ISSUE_IDS[$issueId])) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param list<RegexPatternOccurrence> $originalPatterns
     *
     * @phpstan-param list<LintIssue> $issues
     * @phpstan-param list<OptimizationEntry> $optimizations
     *
     * @phpstan-return list<LintResult>
     */
    private function combineResults(array $issues, array $optimizations, array $originalPatterns): array
    {
        $patternMap = $this->createPatternMap($originalPatterns);
        /** @var array<string, LintResult> $results */
        $results = [];

        $this->addIssuesToResults($issues, $patternMap, $results);
        $this->addOptimizationsToResults($optimizations, $patternMap, $results);

        return array_values($results);
    }

    /**
     * @param list<RegexPatternOccurrence> $originalPatterns
     *
     * @return array<string, string>
     */
    private function createPatternMap(array $originalPatterns): array
    {
        $map = [];
        foreach ($originalPatterns as $pattern) {
            $key = $this->createPatternKey($pattern->file, $pattern->line, $pattern->source);
            $map[$key] = $pattern->displayPattern ?? $pattern->pattern;
        }

        return $map;
    }

    private function createPatternKey(string $file, int $line, ?string $source = null): string
    {
        return $file.':'.$line.':'.($source ?? '');
    }

    /**
     * @phpstan-param list<LintIssue> $issues
     * @phpstan-param array<string, string> $patternMap
     * @phpstan-param array<string, LintResult> $results
     */
    private function addIssuesToResults(array $issues, array $patternMap, array &$results): void
    {
        foreach ($issues as $issue) {
            if ($this->shouldIgnoreIssue($issue)) {
                continue;
            }

            $key = $this->createPatternKey(
                $issue['file'],
                $issue['line'],
                $issue['source'] ?? null,
            );

            $results[$key] ??= $this->createResultStructure($issue, $patternMap[$key] ?? null);
            $results[$key]['issues'][] = $issue;
        }
    }

    /**
     * @phpstan-param list<OptimizationEntry> $optimizations
     * @phpstan-param array<string, string> $patternMap
     * @phpstan-param array<string, LintResult> $results
     */
    private function addOptimizationsToResults(array $optimizations, array $patternMap, array &$results): void
    {
        foreach ($optimizations as $opt) {
            $key = $this->createPatternKey(
                $opt['file'],
                $opt['line'],
                $opt['source'] ?? null,
            );
            $pattern = $patternMap[$key] ?? $opt['optimization']->original ?? null;

            $results[$key] ??= $this->createResultStructure($opt, $pattern);
            $results[$key]['optimizations'][] = $opt;
        }
    }

    /**
     * @phpstan-param LintIssue $issue
     */
    private function shouldIgnoreIssue(array $issue): bool
    {
        $source = $issue['source'] ?? '';
        if (str_starts_with($source, 'route:') || str_starts_with($source, 'validator:')) {
            return false;
        }

        $file = $issue['file'];
        if ('' === $file || !is_file($file)) {
            return false;
        }

        $line = $issue['line'];
        if ($line < 2) {
            return false;
        }

        $content = @file_get_contents($file);
        if (false === $content) {
            return false;
        }

        $lines = explode("\n", $content);
        $prevLineIndex = $line - 2;

        return isset($lines[$prevLineIndex])
            && str_contains($lines[$prevLineIndex], '// @regex-lint-ignore');
    }

    /**
     * @param array{file: string, line: int, source?: string|null} $item
     *
     * @return LintResult
     */
    private function createResultStructure(array $item, ?string $pattern): array
    {
        return [
            'file' => $item['file'],
            'line' => $item['line'],
            'source' => $item['source'] ?? null,
            'pattern' => $pattern,
            'issues' => [],
            'optimizations' => [],
        ];
    }

    /**
     * @param LintStats        $stats
     * @param list<LintResult> $results
     *
     * @return LintStats
     */
    private function updateStatsFromResults(array $stats, array $results): array
    {
        foreach ($results as $result) {
            foreach ($result['issues'] as $issue) {
                if ('error' === $issue['type']) {
                    $stats['errors']++;
                } elseif ('warning' === $issue['type']) {
                    $stats['warnings']++;
                }
            }

            $stats['optimizations'] += \count($result['optimizations']);
        }

        return $stats;
    }
}
