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

use RegexParser\NodeVisitor\ConsoleHighlighterVisitor;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\OptimizationResult;
use RegexParser\ReDoS\ReDoSAnalysis;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

/**
 * Handles regex-related analysis and transformations.
 */
final readonly class RegexAnalysisService
{
    private const PATTERN_DELIMITERS = ['/', '#', '~', '%'];
    private const ISSUE_ID_COMPLEXITY = 'regex.lint.complexity';
    private const ISSUE_ID_REDOS = 'regex.lint.redos';
    private const RISK_LINT_ISSUE_IDS = [
        'regex.lint.quantifier.nested' => true,
        'regex.lint.dotstar.nested' => true,
    ];

    private ReDoSSeverity $redosSeverityThreshold;

    /**
     * @var list<string>
     */
    private array $ignoredPatterns;

    /**
     * @param list<string> $ignoredPatterns
     * @param list<string> $redosIgnoredPatterns
     */
    public function __construct(
        private Regex $regex,
        private ?RegexPatternExtractor $extractor = null,
        private int $warningThreshold = 50,
        string $redosThreshold = ReDoSSeverity::HIGH->value,
        array $ignoredPatterns = [],
        array $redosIgnoredPatterns = [],
        private bool $ignoreParseErrors = false,
    ) {
        $this->redosSeverityThreshold = ReDoSSeverity::tryFrom(strtolower($redosThreshold)) ?? ReDoSSeverity::HIGH;
        $this->ignoredPatterns = $this->buildIgnoredPatterns($ignoredPatterns, $redosIgnoredPatterns);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $excludePaths
     *
     * @return list<RegexPatternOccurrence>
     */
    public function scan(array $paths, array $excludePaths): array
    {
        $extractor = $this->extractor ?? new RegexPatternExtractor(
            new TokenBasedExtractionStrategy(),
        );

        return $extractor->extract($paths, $excludePaths);
    }

    /**
     * @param list<RegexPatternOccurrence> $patterns
     *
     * @return list<array{
     *     type: string,
     *     file: string,
     *     line: int,
     *     column: int,
     *     message: string,
     *     issueId?: string,
     *     hint?: string|null,
     *     source?: string,
     *     analysis?: ReDoSAnalysis,
     *     validation?: \RegexParser\ValidationResult
     * }>
     */
    public function lint(array $patterns, ?callable $progress = null): array
    {
        $issues = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            $source = $occurrence->source;
            if (!$validation->isValid) {
                $message = $validation->error ?? 'Invalid regex.';
                if ($this->ignoreParseErrors && $this->isLikelyPartialRegexError($message)) {
                    if ($progress) {
                        $progress();
                    }

                    continue;
                }

                $issues[] = [
                    'type' => 'error',
                    'file' => $occurrence->file,
                    'line' => $occurrence->line,
                    'column' => 1,
                    'message' => $message,
                    'source' => $source,
                    'validation' => $validation,
                ];

                if ($progress) {
                    $progress();
                }

                continue;
            }

            $ast = $this->regex->parse($occurrence->pattern);
            $linter = new LinterNodeVisitor();
            $ast->accept($linter);
            $skipRiskAnalysis = $this->shouldSkipRiskAnalysis($occurrence);

            foreach ($linter->getIssues() as $issue) {
                if ($skipRiskAnalysis && isset(self::RISK_LINT_ISSUE_IDS[$issue->id])) {
                    continue;
                }

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

            if (!$skipRiskAnalysis) {
                if ($validation->complexityScore >= $this->warningThreshold) {
                    $issues[] = [
                        'type' => 'warning',
                        'file' => $occurrence->file,
                        'line' => $occurrence->line,
                        'column' => 1,
                        'issueId' => self::ISSUE_ID_COMPLEXITY,
                        'message' => \sprintf('Pattern is complex (score: %d).', $validation->complexityScore),
                        'source' => $source,
                    ];
                }

                $redos = $this->regex->redos($occurrence->pattern, $this->redosSeverityThreshold);
                if ($redos->exceedsThreshold($this->redosSeverityThreshold)) {
                    $issues[] = [
                        'type' => 'error',
                        'file' => $occurrence->file,
                        'line' => $occurrence->line,
                        'column' => 1,
                        'issueId' => self::ISSUE_ID_REDOS,
                        'message' => \sprintf(
                            'Pattern may be vulnerable to ReDoS (severity: %s).',
                            strtoupper($redos->severity->value),
                        ),
                        'source' => $source,
                        'analysis' => $redos,
                    ];
                }
            }

            if ($progress) {
                $progress();
            }
        }

        return $issues;
    }

    /**
     * @param list<RegexPatternOccurrence> $patterns
     *
     * @return list<array{file: string, line: int, analysis: ReDoSAnalysis}>
     */
    public function analyzeRedos(array $patterns, ReDoSSeverity $threshold): array
    {
        $issues = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            if (!$validation->isValid) {
                continue;
            }

            $analysis = $this->regex->redos($occurrence->pattern);
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
     * @param list<RegexPatternOccurrence>                           $patterns
     * @param array{digits?: bool, word?: bool, strictRanges?: bool} $optimizationConfig
     *
     * @return list<array{
     *     file: string,
     *     line: int,
     *     optimization: OptimizationResult,
     *     savings: int,
     *     source?: string
     * }>
     */
    public function suggestOptimizations(array $patterns, int $minSavings, array $optimizationConfig = []): array
    {
        $suggestions = [];

        foreach ($patterns as $occurrence) {
            $validation = $this->regex->validate($occurrence->pattern);
            $source = $occurrence->source;
            if (!$validation->isValid) {
                continue;
            }

            try {
                $optimization = $this->regex->optimize($occurrence->pattern, $optimizationConfig);
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
        $ast = $this->regex->parse($pattern);

        return $ast->accept(new ConsoleHighlighterVisitor());
    }

    private function shouldSkipRiskAnalysis(RegexPatternOccurrence $occurrence): bool
    {
        $rawPattern = $occurrence->displayPattern ?? $occurrence->pattern;
        $fragment = $this->extractFragment($rawPattern);
        $body = $this->trimPatternBody($occurrence->pattern);

        return $this->isIgnored($fragment)
            || $this->isIgnored($body)
            || $this->isTriviallySafe($fragment)
            || $this->isTriviallySafe($body);
    }

    private function extractFragment(string $pattern): string
    {
        if ('' === $pattern) {
            return '';
        }

        $first = $pattern[0];
        $last = $pattern[-1];

        if ($first === $last && \in_array($first, self::PATTERN_DELIMITERS, true)) {
            $pattern = substr($pattern, 1, -1);
        }

        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }

    private function trimPatternBody(string $pattern): string
    {
        if ('' === $pattern) {
            return '';
        }

        $first = $pattern[0];
        $last = $pattern[-1];

        if ($first === $last) {
            $pattern = substr($pattern, 1, -1);
        }

        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }

    private function isIgnored(string $body): bool
    {
        if ('' === $body) {
            return false;
        }

        return \in_array($body, $this->ignoredPatterns, true);
    }

    private function isTriviallySafe(string $body): bool
    {
        if ('' === $body) {
            return false;
        }

        $parts = explode('|', $body);
        if (\count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (!preg_match('#^[A-Za-z0-9._-]+$#', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $userIgnored
     * @param list<string> $redosIgnored
     *
     * @return list<string>
     */
    private function buildIgnoredPatterns(array $userIgnored, array $redosIgnored): array
    {
        return array_values(array_unique([...$redosIgnored, ...$userIgnored]));
    }

    private function isLikelyPartialRegexError(string $errorMessage): bool
    {
        $indicators = [
            'No closing delimiter',
            'Regex too short',
            'Unknown modifier',
            'Unexpected end',
        ];

        foreach ($indicators as $indicator) {
            if (false !== stripos($errorMessage, (string) $indicator)) {
                return true;
            }
        }

        return false;
    }
}
