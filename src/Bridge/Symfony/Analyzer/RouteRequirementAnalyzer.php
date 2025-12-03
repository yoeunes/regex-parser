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

use RegexParser\Regex;
use Symfony\Component\Routing\RouteCollection;

/**
 * Analyses Symfony route requirements and reports regex issues.
 *
 * @internal
 */
final readonly class RouteRequirementAnalyzer
{
    private const array PATTERN_DELIMITERS = ['/', '#', '~', '%'];

    public function __construct(
        private Regex $regex,
        private int $warningThreshold,
        private int $redosThreshold,
    ) {}

    /**
     * @return list<RegexAnalysisIssue>
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

                $normalizedPattern = $this->normalizePattern($pattern);
                $result = $this->regex->validate($normalizedPattern);

                if (!$result->isValid) {
                    $issues[] = new RegexAnalysisIssue(
                        \sprintf('Route "%s" requirement "%s" is invalid: %s (pattern: %s)', (string) $name, $parameter, $result->error ?? 'unknown error', $this->formatPattern($normalizedPattern)),
                        true,
                    );

                    continue;
                }

                if ($result->complexityScore >= $this->redosThreshold) {
                    $issues[] = new RegexAnalysisIssue(
                        \sprintf('Route "%s" requirement "%s" may be vulnerable to ReDoS (score: %d, pattern: %s).', (string) $name, $parameter, $result->complexityScore, $this->formatPattern($normalizedPattern)),
                        true,
                    );

                    continue;
                }

                if ($result->complexityScore >= $this->warningThreshold) {
                    $issues[] = new RegexAnalysisIssue(
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

        $delimiter = '#';
        $body = str_replace($delimiter, '\\'.$delimiter, $pattern);

        return $delimiter.'^'.$body.'$'.$delimiter;
    }

    private function formatPattern(string $pattern): string
    {
        if (\strlen($pattern) <= 80) {
            return $pattern;
        }

        return substr($pattern, 0, 77).'...';
    }
}
