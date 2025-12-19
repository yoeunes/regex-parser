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

use RegexParser\Bridge\Symfony\Routing\RouteControllerFileResolver;
use RegexParser\Bridge\Symfony\Routing\RouteRequirementNormalizer;
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
        private RouteRequirementNormalizer $patternNormalizer,
        private RouteControllerFileResolver $routeFileResolver,
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
            $file = $this->routeFileResolver->resolve($route);

            foreach ($route->getRequirements() as $parameter => $requirement) {
                if (!\is_scalar($requirement)) {
                    continue;
                }

                $pattern = trim((string) $requirement);
                if ('' === $pattern) {
                    continue;
                }

                $fragment = $this->patternInspector->extractFragment($pattern);
                $normalizedPattern = $this->patternNormalizer->normalize($pattern);
                $body = $this->patternInspector->trimPatternBody($normalizedPattern);

                if ($this->isIgnored($fragment) || $this->isIgnored($body)) {
                    continue;
                }

                $isTrivial = $this->patternInspector->isTriviallySafe($fragment)
                    || $this->patternInspector->isTriviallySafe($body);

                $result = $this->regex->validate($normalizedPattern);

                $id = $file ? $file.' (Route: '.$name.')' : 'Route: '.$name;

                if ($isTrivial) {
                    if (!$result->isValid) {
                        $issues[] = new AnalysisIssue(
                            \sprintf('requirement "%s" in route "%s" is invalid: %s', $parameter, $name, $result->error ?? 'unknown error'),
                            true,
                            $pattern,
                            $id,
                        );
                    }

                    continue;
                }

                if (!$result->isValid) {
                    $issues[] = new AnalysisIssue(
                        \sprintf('requirement "%s" in route "%s" is invalid: %s', $parameter, $name, $result->error ?? 'unknown error'),
                        true,
                        $pattern,
                        $id,
                    );

                    continue;
                }

                $redos = $this->regex->analyzeReDoS($normalizedPattern);
                if ($redos->exceedsThreshold($this->redosSeverityThreshold)) {
                    $issues[] = new AnalysisIssue(
                        \sprintf(
                            'requirement "%s" may be vulnerable to ReDoS (severity: %s).',
                            $parameter,
                            strtoupper($redos->severity->value),
                        ),
                        true,
                        $pattern,
                        $id,
                    );

                    continue;
                }

                if ($result->complexityScore >= $this->warningThreshold) {
                    $issues[] = new AnalysisIssue(
                        \sprintf('requirement "%s" in route "%s" is complex (score: %d).', $parameter, $name, $result->complexityScore),
                        false,
                        $pattern,
                        $id,
                    );
                }
            }
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
}
