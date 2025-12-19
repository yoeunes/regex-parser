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

use RegexParser\Bridge\Symfony\Analyzer\AnalysisIssue;
use RegexParser\Bridge\Symfony\Extractor\RegexPatternOccurrence;
use RegexParser\Bridge\Symfony\Extractor\RegexPatternSourceCollection;
use RegexParser\Bridge\Symfony\Extractor\RegexPatternSourceContext;

/**
 * Aggregates pattern sources and produces lint results.
 *
 * @internal
 */
final readonly class RegexLintService
{
    /**
     * @param iterable<RegexLintIssueProviderInterface> $issueProviders
     */
    public function __construct(
        private RegexAnalysisService $analysis,
        private RegexPatternSourceCollection $sources,
        private iterable $issueProviders = [],
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
        [$extraResults, $enabledProviders] = $this->collectAdditionalResultsAndEnabledProviders($request);

        $issues = $this->analysis->lint($patterns, $progress);
        $issues = $this->filterLintIssues($issues, $enabledProviders);

        $optimizations = $this->analysis->suggestOptimizations($patterns, $request->minSavings);

        $results = $this->combineResults($issues, $optimizations, $patterns);

        if (!empty($extraResults)) {
            $results = [...$results, ...$extraResults];
        }

        $stats = $this->updateStatsFromResults($this->createStats(), $results);

        return new RegexLintReport($results, $stats);
    }

    /**
     * @return array{errors: int, warnings: int, optimizations: int}
     */
    private function createStats(): array
    {
        return ['errors' => 0, 'warnings' => 0, 'optimizations' => 0];
    }

    /**
     * @param array<int, array{
     *     type: string,
     *     file: string,
     *     line: int,
     *     column: int,
     *     message: string,
     *     issueId?: string,
     *     hint?: string|null,
     *     source?: string
     * }> $issues
     */
    private function filterLintIssues(array $issues, array $enabledProviders): array
    {
        $validatorEnabled = $enabledProviders['validators'] ?? false;

        return array_values(array_filter($issues, static function (array $issue) use ($validatorEnabled): bool {
            $source = (string) ($issue['source'] ?? '');

            if (str_starts_with($source, 'route:')) {
                return !str_contains((string) $issue['message'], 'ReDoS')
                    && !str_contains((string) $issue['message'], 'Nested quantifiers');
            }

            if ($validatorEnabled && str_starts_with($source, 'validator:') && 'error' === $issue['type']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array{
     *     0: array<int, array{
     *         file: string,
     *         line: int,
     *         source?: string|null,
     *         pattern: string|null,
     *         issues: array<int, array{type: string, message: string, file: string, line: int, hint?: string|null, source?: string}>,
     *         optimizations: array<int, array{file: string, line: int, optimization: object, savings: int, source?: string}>
     *     }>,
     *     1: array<string, bool>
     * }
     */
    private function collectAdditionalResultsAndEnabledProviders(RegexLintRequest $request): array
    {
        $results = [];
        $enabledProviders = [];

        foreach ($this->issueProviders as $provider) {
            if (!$provider instanceof RegexLintIssueProviderInterface) {
                continue;
            }

            if (!$request->isSourceEnabled($provider->getName())) {
                continue;
            }

            if (!$provider->isSupported()) {
                continue;
            }

            $enabledProviders[$provider->getName()] = true;
            $results = [...$results, ...$this->convertAnalysisIssuesToResults(
                $provider->analyze(),
                $provider->getLabel(),
            )];
        }

        return [$results, $enabledProviders];
    }

    /**
     * @param array<int, array{
     *     type: string,
     *     file: string,
     *     line: int,
     *     column: int,
     *     message: string,
     *     issueId?: string,
     *     hint?: string|null,
     *     source?: string
     * }> $issues
     * @param array<int, array{
     *     file: string,
     *     line: int,
     *     optimization: object,
     *     savings: int,
     *     source?: string
     * }> $optimizations
     * @param list<RegexPatternOccurrence> $originalPatterns
     *
     * @return array<int, array{
     *     file: string,
     *     line: int,
     *     source?: string|null,
     *     pattern: string|null,
     *     issues: array<int, array{type: string, message: string, file: string, line: int, hint?: string|null, source?: string}>,
     *     optimizations: array<int, array{file: string, line: int, optimization: object, savings: int, source?: string}>
     * }>
     */
    private function combineResults(array $issues, array $optimizations, array $originalPatterns): array
    {
        $patternMap = $this->createPatternMap($originalPatterns);
        $results = [];

        $this->addIssuesToResults($issues, $patternMap, $results);
        $this->addOptimizationsToResults($optimizations, $patternMap, $results);

        return array_values($results);
    }

    /**
     * @param list<RegexPatternOccurrence> $originalPatterns
     */
    private function createPatternMap(array $originalPatterns): array
    {
        $map = [];
        foreach ($originalPatterns as $pattern) {
            $key = $this->createPatternKey($pattern->file, $pattern->line, $pattern->source ?? null);
            $map[$key] = $pattern->displayPattern ?? $pattern->pattern;
        }

        return $map;
    }

    private function createPatternKey(string $file, int $line, ?string $source = null): string
    {
        return $file.':'.$line.':'.($source ?? '');
    }

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

    private function shouldIgnoreIssue(array $issue): bool
    {
        $source = (string) ($issue['source'] ?? '');
        if (str_starts_with($source, 'route:') || str_starts_with($source, 'validator:')) {
            return false;
        }

        $file = $issue['file'] ?? null;
        if (!\is_string($file) || '' === $file || !is_file($file)) {
            return false;
        }

        $line = (int) ($issue['line'] ?? 0);
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
     * @param list<AnalysisIssue> $issues
     */
    private function convertAnalysisIssuesToResults(array $issues, string $category): array
    {
        return array_map(
            fn ($issue, $index) => $this->convertAnalysisIssueToResult($issue, $index, $category),
            $issues,
            array_keys($issues),
        );
    }

    private function convertAnalysisIssueToResult(AnalysisIssue $issue, int $index, string $category): array
    {
        [$file, $location] = $this->extractFileAndLocation($issue->id ?? null, $category);
        [$pattern, $message] = $this->extractPatternAndMessage($issue->pattern, $issue->message, $location);

        return [
            'file' => $file,
            'line' => $index + 1,
            'pattern' => $pattern,
            'location' => $location,
            'issues' => [
                [
                    'type' => $issue->isError ? 'error' : 'warning',
                    'message' => $message,
                    'file' => $file,
                    'line' => $index + 1,
                ],
            ],
            'optimizations' => [],
        ];
    }

    private function extractFileAndLocation(?string $id, string $category): array
    {
        if (!$id) {
            return [$category, null];
        }

        if (str_contains($id, ' (Route: ')) {
            [$file, $route] = explode(' (Route: ', $id, 2);

            return [$file, 'Route: '.rtrim($route, ')')];
        }

        return [$category, $id];
    }

    private function extractPatternAndMessage(?string $pattern, string $message, ?string $location): array
    {
        if (!$pattern && preg_match('/pattern: ([^)]+)/', $message, $matches)) {
            $pattern = trim($matches[1], '#');
            $message = preg_replace('/ \(pattern: [^)]+\)/', '', $message);
        }

        if (!$location && preg_match('/Route "([^"]+)"/', (string) $message, $matches)) {
            $message = preg_replace('/Route "[^"]+" /', '', (string) $message);
        }

        return [$pattern, $message];
    }

    private function updateStatsFromResults(array $stats, array $results): array
    {
        foreach ($results as $result) {
            $stats['errors'] += \count(array_filter($result['issues'], fn ($issue) => 'error' === $issue['type']));
            $stats['warnings'] += \count(array_filter($result['issues'], fn ($issue) => 'warning' === $issue['type']));
            $stats['optimizations'] += \count($result['optimizations']);
        }

        return $stats;
    }
}
