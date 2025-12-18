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
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Validates regex usage in Symfony routes.
 */
final readonly class RouteValidationService
{
    public function __construct(
        private RegexAnalysisService $analysis,
        private ?RouterInterface $router = null,
    ) {}

    public function isSupported(): bool
    {
        return null !== $this->router;
    }

    public function analyze(): array
    {
        if (!$this->isSupported()) {
            return [];
        }

        $patterns = [];
        $patternMap = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            $file = $this->getRouteFile($route);

            foreach ($route->getRequirements() as $parameter => $requirement) {
                if (!\is_scalar($requirement)) {
                    continue;
                }

                $pattern = trim((string) $requirement);
                if ('' === $pattern) {
                    continue;
                }

                $key = $file.' (Route: '.$name.')';
                $normalized = $this->normalizePattern($pattern);
                $patterns[] = new RegexPatternOccurrence($normalized, $key, 1, $normalized);
                $patternMap[$key] = ['route' => $name, 'param' => $parameter, 'pattern' => $pattern, 'file' => $file];
            }
        }

        $lintIssues = $this->analysis->lint($patterns);
        $optimizations = $this->analysis->suggestOptimizations($patterns, 1); // minSavings

        $analysisIssues = [];

        foreach ($lintIssues as $issue) {
            $key = $issue['file'];
            if (isset($patternMap[$key])) {
                $data = $patternMap[$key];
                // Skip ReDoS and nested quantifiers warnings for routes, as they may not be relevant
                if (str_contains($issue['message'], 'ReDoS') || str_contains($issue['message'], 'Nested quantifiers')) {
                    continue;
                }
                $id = $data['file'] ? $data['file'].' (Route: '.$data['route'].')' : 'Route: '.$data['route'];
                $analysisIssues[] = new AnalysisIssue(
                    $issue['message'],
                    'error' === $issue['type'],
                    $data['pattern'],
                    $id,
                );
            }
        }

        return $analysisIssues;
    }

    private function normalizePattern(string $pattern): string
    {
        $firstChar = $pattern[0] ?? '';

        if (\in_array($firstChar, ['/', '#', '~', '%'], true)) {
            return $pattern;
        }

        if (str_starts_with($pattern, '^') && str_ends_with($pattern, '$')) {
            return '#'.$pattern.'#';
        }

        $delimiter = '#';
        $body = str_replace($delimiter, '\\'.$delimiter, $pattern);

        return $delimiter.'^'.$body.'$'.$delimiter;
    }

    private function getRouteFile(Route $route): ?string
    {
        $controller = $route->getDefault('_controller');
        if (!\is_string($controller)) {
            return null;
        }

        // Handle controller as class::method
        if (str_contains($controller, '::')) {
            [$class] = explode('::', $controller, 2);
        } else {
            $class = $controller;
        }

        if (!class_exists($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);

        return $reflection->getFileName();
    }
}
