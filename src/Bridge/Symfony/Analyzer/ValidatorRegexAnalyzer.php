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

namespace RegexParser\Bridge\Symfony\Analyzer;

use RegexParser\Bridge\Symfony\Validator\ValidatorPattern;
use RegexParser\Bridge\Symfony\Validator\ValidatorPatternProvider;
use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;

/**
 * Analyses Symfony Validator metadata for Regex constraints.
 *
 * @internal
 */
final readonly class ValidatorRegexAnalyzer
{
    private ReDoSSeverity $redosSeverityThreshold;

    /**
     * @var list<string>
     */
    private array $ignoredPatterns;

    /**
     * @param list<string> $ignoredPatterns
     */
    public function __construct(
        private Regex $regex,
        private RegexPatternInspector $patternInspector,
        private ValidatorPatternProvider $patternProvider,
        private int $warningThreshold,
        string $redosThreshold = ReDoSSeverity::HIGH->value,
        array $ignoredPatterns = [],
    ) {
        $this->redosSeverityThreshold = ReDoSSeverity::tryFrom(strtolower($redosThreshold)) ?? ReDoSSeverity::HIGH;
        $this->ignoredPatterns = $this->buildIgnoredPatterns($ignoredPatterns);
    }

    /**
     * @return list<AnalysisIssue>
     */
    public function analyze(): array
    {
        if (!$this->patternProvider->isSupported()) {
            return [];
        }

        $issues = [];
        foreach ($this->patternProvider->collect() as $pattern) {
            $issues = [...$issues, ...$this->analyzePattern($pattern)];
        }

        return $issues;
    }

    public function isSupported(): bool
    {
        return $this->patternProvider->isSupported();
    }

    /**
     * @return list<AnalysisIssue>
     */
    private function analyzePattern(ValidatorPattern $pattern): array
    {
        $issues = [];
        $rawPattern = $pattern->pattern;
        $fragment = $this->patternInspector->extractFragment($rawPattern);
        $body = $this->patternInspector->trimPatternBody($rawPattern);

        if ($this->isIgnored($fragment) || $this->isIgnored($body)) {
            return [];
        }

        $isTrivial = $this->patternInspector->isTriviallySafe($fragment)
            || $this->patternInspector->isTriviallySafe($body);

        $result = $this->regex->validate($rawPattern);
        $id = $this->buildIssueId($pattern);

        if ($isTrivial) {
            if (!$result->isValid) {
                $issues[] = new AnalysisIssue(
                    \sprintf(
                        'Validator "%s" pattern is invalid: %s (pattern: %s)',
                        $pattern->source,
                        $result->error ?? 'unknown error',
                        $this->patternInspector->formatPattern($rawPattern),
                    ),
                    true,
                    $rawPattern,
                    $id,
                );
            }

            return $issues;
        }

        if (!$result->isValid) {
            $issues[] = new AnalysisIssue(
                \sprintf(
                    'Validator "%s" pattern is invalid: %s (pattern: %s)',
                    $pattern->source,
                    $result->error ?? 'unknown error',
                    $this->patternInspector->formatPattern($rawPattern),
                ),
                true,
                $rawPattern,
                $id,
            );

            return $issues;
        }

        $redos = $this->regex->analyzeReDoS($rawPattern);
        if ($redos->exceedsThreshold($this->redosSeverityThreshold)) {
            $issues[] = new AnalysisIssue(
                \sprintf(
                    'Validator "%s" pattern may be vulnerable to ReDoS (severity: %s, pattern: %s).',
                    $pattern->source,
                    strtoupper($redos->severity->value),
                    $this->patternInspector->formatPattern($rawPattern),
                ),
                true,
                $rawPattern,
                $id,
            );

            return $issues;
        }

        if ($result->complexityScore >= $this->warningThreshold) {
            $issues[] = new AnalysisIssue(
                \sprintf(
                    'Validator "%s" pattern is complex (score: %d, pattern: %s).',
                    $pattern->source,
                    $result->complexityScore,
                    $this->patternInspector->formatPattern($rawPattern),
                ),
                false,
                $rawPattern,
                $id,
            );
        }

        return $issues;
    }

    private function isIgnored(string $body): bool
    {
        if ('' === $body) {
            return false;
        }

        return \in_array($body, $this->ignoredPatterns, true);
    }

    /**
     * @param list<string> $userIgnored
     *
     * @return list<string>
     */
    private function buildIgnoredPatterns(array $userIgnored): array
    {
        return array_values(array_unique([...$this->regex->getRedosIgnoredPatterns(), ...$userIgnored]));
    }

    private function buildIssueId(ValidatorPattern $pattern): ?string
    {
        $file = $pattern->file;
        if (null === $file || '' === $file) {
            return 'Validator: '.$pattern->source;
        }

        return $file.' (Validator: '.$pattern->source.')';
    }
}
