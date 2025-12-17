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

use RegexParser\ReDoS\ReDoSSeverity;
use RegexParser\Regex;
use Symfony\Component\Routing\RouteCollection;

/**
 * Analyses Symfony route requirements and reports regex issues.
 *
 * @internal
 */
final readonly class RouteRequirementAnalyzer
{
    private const PATTERN_DELIMITERS = ['/', '#', '~', '%'];

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
    public function analyze(RouteCollection $routes): array
    {
        $issues = [];

        foreach ($routes as $name => $route) {
            foreach ($route->getRequirements() as $parameter => $requirement) {
                if (!\is_scalar($requirement)) {
                    continue;
                }

                $pattern = trim((string) $requirement);
                if ('' === $pattern) {
                    continue;
                }

                $fragment = $this->extractFragment($pattern);
                $normalizedPattern = $this->normalizePattern($pattern);
                $body = $this->trimPatternBody($normalizedPattern);

                if ($this->isIgnored($fragment) || $this->isIgnored($body)) {
                    continue;
                }

                $isTrivial = $this->isTriviallySafe($fragment) || $this->isTriviallySafe($body);

                $result = $this->regex->validate($normalizedPattern);

                if ($isTrivial) {
                    if (!$result->isValid) {
                        $issues[] = new AnalysisIssue(\sprintf('Route "%s" requirement "%s" is invalid: %s (pattern: %s)', (string) $name, $parameter, $result->error ?? 'unknown error', $this->formatPattern($normalizedPattern)), true);
                    }

                    continue;
                }

                if (!$result->isValid) {
                    $issues[] = new AnalysisIssue(
                        \sprintf('Route "%s" requirement "%s" is invalid: %s (pattern: %s)', (string) $name, $parameter, $result->error ?? 'unknown error', $this->formatPattern($normalizedPattern)),
                        true,
                    );

                    continue;
                }

                $redos = $this->regex->analyzeReDoS($normalizedPattern);
                if ($redos->exceedsThreshold($this->redosSeverityThreshold)) {
                    $issues[] = new AnalysisIssue(
                        \sprintf(
                            'Route "%s" requirement "%s" may be vulnerable to ReDoS (severity: %s, pattern: %s).',
                            (string) $name,
                            $parameter,
                            strtoupper($redos->severity->value),
                            $this->formatPattern($normalizedPattern),
                        ),
                        true,
                    );

                    continue;
                }

                if ($result->complexityScore >= $this->warningThreshold) {
                    $issues[] = new AnalysisIssue(
                        \sprintf('Route "%s" requirement "%s" is complex (score: %d, pattern: %s).', (string) $name, $parameter, $result->complexityScore, $this->formatPattern($normalizedPattern)),
                        false,
                    );
                }
            }
        }

        return $issues;
    }

    private function normalizePattern(string $pattern): string
    {
        $firstChar = $pattern[0] ?? '';

        if (\in_array($firstChar, self::PATTERN_DELIMITERS, true)) {
            return $pattern;
        }

        if (str_starts_with($pattern, '^') && str_ends_with($pattern, '$')) {
            return '#'.$pattern.'#';
        }

        $delimiter = '#';
        $body = str_replace($delimiter, '\\'.$delimiter, $pattern);

        return $delimiter.'^'.$body.'$'.$delimiter;
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

    private function formatPattern(string $pattern): string
    {
        if (\strlen($pattern) <= 80) {
            return $pattern;
        }

        return substr($pattern, 0, 77).'...';
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

        return 1 === preg_match('#^[A-Za-z0-9._-]+(?:\|[A-Za-z0-9._-]+)+$#', $body);
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
}
