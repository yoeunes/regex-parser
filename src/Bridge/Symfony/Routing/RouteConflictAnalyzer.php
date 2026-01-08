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

namespace RegexParser\Bridge\Symfony\Routing;

use RegexParser\Automata\AstToNfaTransformer;
use RegexParser\Automata\CharSet;
use RegexParser\Automata\Dfa;
use RegexParser\Automata\DfaBuilder;
use RegexParser\Automata\MatchMode;
use RegexParser\Automata\RegularSubsetValidator;
use RegexParser\Automata\SolverOptions;
use RegexParser\Exception\ComplexityException;
use RegexParser\Regex;
use RegexParser\RegexPattern;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Detects Symfony route conflicts using RegexParser automata logic.
 *
 * @internal
 *
 * @phpstan-import-type RouteDescriptor from RouteConflictReport
 * @phpstan-import-type RouteConflict from RouteConflictReport
 * @phpstan-import-type RouteSkip from RouteConflictReport
 */
final readonly class RouteConflictAnalyzer
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_FLAGS = ['i', 's'];

    /**
     * @var array<int, string>
     */
    private const IGNORED_FLAGS = ['D'];

    public function __construct(
        private Regex $regex,
        private ?RegularSubsetValidator $validator = null,
        private ?DfaBuilder $dfaBuilder = null,
    ) {}

    public function analyze(RouteCollection $collection, bool $includeOverlaps = true): RouteConflictReport
    {
        $routes = [];
        $skippedRoutes = [];
        $routesWithConditions = [];
        $routesWithUnsupportedHosts = [];
        $index = 0;

        $options = new SolverOptions(matchMode: MatchMode::FULL);

        foreach ($collection as $name => $route) {
            $index++;
            $descriptor = $this->buildDescriptor(
                (string) $name,
                $route,
                $index,
                $options,
                $skippedRoutes,
                $routesWithConditions,
                $routesWithUnsupportedHosts,
            );
            if (null !== $descriptor) {
                $routes[] = $descriptor;
            }
        }

        $conflicts = [];
        $shadowed = 0;
        $overlaps = 0;
        $skippedPairs = 0;
        $count = \count($routes);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $left = $routes[$i];
                $right = $routes[$j];

                if (!$this->methodsOverlap($left['methods'], $right['methods'])) {
                    continue;
                }

                if (!$this->schemesOverlap($left['schemes'], $right['schemes'])) {
                    continue;
                }

                if (!$this->prefixOverlaps($left['staticPrefix'], $right['staticPrefix'])) {
                    continue;
                }

                if (!$this->hostsOverlap($left, $right, $skippedPairs)) {
                    continue;
                }

                $example = $this->findExample(
                    $left['pathDfa'],
                    $right['pathDfa'],
                    static fn (bool $leftAccept, bool $rightAccept): bool => $leftAccept && $rightAccept,
                );

                if (null === $example) {
                    continue;
                }

                $isSubset = null === $this->findExample(
                    $right['pathDfa'],
                    $left['pathDfa'],
                    static fn (bool $leftAccept, bool $rightAccept): bool => $leftAccept && !$rightAccept,
                );

                if ($isSubset) {
                    $shadowed++;
                } else {
                    $overlaps++;
                }

                if (!$includeOverlaps && !$isSubset) {
                    continue;
                }

                $conflicts[] = [
                    'route' => $left,
                    'conflict' => $right,
                    'type' => $isSubset ? 'shadowed' : 'overlap',
                    'example' => $example,
                    'methods' => $this->intersectMethods($left['methods'], $right['methods']),
                    'schemes' => $this->intersectSchemes($left['schemes'], $right['schemes']),
                    'notes' => $this->buildNotes($left, $right),
                ];
            }
        }

        $stats = [
            'routes' => $index,
            'conflicts' => \count($conflicts),
            'shadowed' => $shadowed,
            'overlaps' => $overlaps,
            'skipped_routes' => \count($skippedRoutes),
            'skipped_pairs' => $skippedPairs,
        ];

        return new RouteConflictReport(
            $conflicts,
            $skippedRoutes,
            $stats,
            $routesWithConditions,
            $routesWithUnsupportedHosts,
        );
    }

    /**
     * @param array<RouteSkip> $skippedRoutes
     * @param array<string>    $routesWithConditions
     * @param array<string>    $routesWithUnsupportedHosts
     *
     * @phpstan-return RouteDescriptor|null
     */
    private function buildDescriptor(
        string $name,
        Route $route,
        int $index,
        SolverOptions $options,
        array &$skippedRoutes,
        array &$routesWithConditions,
        array &$routesWithUnsupportedHosts,
    ): ?array {
        try {
            $compiled = $route->compile();
        } catch (\Throwable $exception) {
            $skippedRoutes[] = [
                'route' => $name,
                'reason' => 'Route compile failed: '.$exception->getMessage(),
            ];

            return null;
        }

        $pathRegex = $compiled->getRegex();
        try {
            $pathNormalization = $this->normalizePattern($pathRegex);
        } catch (\Throwable $exception) {
            $skippedRoutes[] = [
                'route' => $name,
                'reason' => 'Route regex normalization failed: '.$exception->getMessage(),
            ];

            return null;
        }

        if ([] !== $pathNormalization['unsupportedFlags']) {
            $skippedRoutes[] = [
                'route' => $name,
                'reason' => 'Unsupported regex flags: '.implode(', ', $pathNormalization['unsupportedFlags']),
            ];

            return null;
        }

        try {
            $pathDfa = $this->buildDfa($pathNormalization['pattern'], $options);
        } catch (ComplexityException $exception) {
            $skippedRoutes[] = [
                'route' => $name,
                'reason' => $exception->getMessage(),
            ];

            return null;
        }

        $hostRegex = $this->resolveHostRegex($compiled);
        $hostPattern = null;
        $hostDfa = null;
        $hostValue = $route->getHost();
        $hasHostRequirement = \is_string($hostValue) && '' !== $hostValue;
        $hostUnsupported = false;

        if ($hasHostRequirement && null === $hostRegex) {
            $hostUnsupported = true;
            $routesWithUnsupportedHosts[] = $name;
        } elseif ($hasHostRequirement) {
            try {
                $hostNormalization = $this->normalizePattern($hostRegex);
            } catch (\Throwable) {
                $hostNormalization = null;
                $hostUnsupported = true;
                $routesWithUnsupportedHosts[] = $name;
            }

            if (null !== $hostNormalization && [] !== $hostNormalization['unsupportedFlags']) {
                $hostUnsupported = true;
                $routesWithUnsupportedHosts[] = $name;
            } elseif (null !== $hostNormalization) {
                try {
                    $hostPattern = $hostNormalization['pattern'];
                    $hostDfa = $this->buildDfa($hostPattern, $options);
                } catch (ComplexityException) {
                    $hostUnsupported = true;
                    $routesWithUnsupportedHosts[] = $name;
                }
            }
        }

        $condition = $route->getCondition();
        $hasCondition = \is_string($condition) && '' !== trim($condition);
        if ($hasCondition) {
            $routesWithConditions[] = $name;
        }

        return [
            'name' => $name,
            'path' => $route->getPath(),
            'pathPattern' => $pathNormalization['pattern'],
            'pathDfa' => $pathDfa,
            'staticPrefix' => $this->resolveStaticPrefix($compiled, $route),
            'staticSegments' => $this->extractStaticSegments($route->getPath()),
            'methods' => $this->normalizeMethods($route->getMethods()),
            'schemes' => $this->normalizeSchemes($route->getSchemes()),
            'hostPattern' => $hostPattern,
            'hostDfa' => $hostDfa,
            'hasHostRequirement' => $hasHostRequirement,
            'hostUnsupported' => $hostUnsupported,
            'hasCondition' => $hasCondition,
            'index' => $index,
        ];
    }

    private function resolveHostRegex(object $compiled): ?string
    {
        if (!method_exists($compiled, 'getHostRegex')) {
            return null;
        }

        $hostRegex = $compiled->getHostRegex();
        if (!\is_string($hostRegex) || '' === $hostRegex) {
            return null;
        }

        return $hostRegex;
    }

    private function resolveStaticPrefix(object $compiled, Route $route): string
    {
        if (method_exists($compiled, 'getStaticPrefix')) {
            $prefix = $compiled->getStaticPrefix();
            if (\is_string($prefix)) {
                return $prefix;
            }
        }

        return $route->getPath();
    }

    /**
     * @return array{pattern: string, ignoredFlags: array<int, string>, unsupportedFlags: array<int, string>}
     */
    private function normalizePattern(string $regex): array
    {
        $pattern = RegexPattern::fromDelimited($regex);
        $flags = $pattern->flags;
        $normalizedFlags = '';
        $ignored = [];
        $unsupported = [];

        foreach (\str_split($flags) as $flag) {
            if (\in_array($flag, self::SUPPORTED_FLAGS, true)) {
                $normalizedFlags .= $flag;

                continue;
            }

            if (\in_array($flag, self::IGNORED_FLAGS, true)) {
                $ignored[] = $flag;

                continue;
            }

            $unsupported[] = $flag;
        }

        $normalized = RegexPattern::fromRaw($pattern->pattern, $normalizedFlags, $pattern->delimiter);

        return [
            'pattern' => $normalized->toString(),
            'ignoredFlags' => $ignored,
            'unsupportedFlags' => array_values(array_unique($unsupported)),
        ];
    }

    private function buildDfa(string $pattern, SolverOptions $options): Dfa
    {
        $ast = $this->regex->parse($pattern);

        $validator = $this->validator ?? new RegularSubsetValidator();
        $validator->assertSupported($ast, $pattern, $options);

        $transformer = new AstToNfaTransformer($pattern);
        $nfa = $transformer->transform($ast, $options);

        $dfaBuilder = $this->dfaBuilder ?? new DfaBuilder();

        return $dfaBuilder->determinize($nfa, $options);
    }

    /**
     * @phpstan-param RouteDescriptor $left
     * @phpstan-param RouteDescriptor $right
     */
    private function hostsOverlap(array $left, array $right, int &$skippedPairs): bool
    {
        if (!$left['hasHostRequirement'] || !$right['hasHostRequirement']) {
            return true;
        }

        if ($left['hostUnsupported'] || $right['hostUnsupported']) {
            return true;
        }

        if (null === $left['hostDfa'] || null === $right['hostDfa']) {
            $skippedPairs++;

            return true;
        }

        $example = $this->findExample(
            $left['hostDfa'],
            $right['hostDfa'],
            static fn (bool $leftAccept, bool $rightAccept): bool => $leftAccept && $rightAccept,
        );

        return null !== $example;
    }

    /**
     * @phpstan-param RouteDescriptor $left
     * @phpstan-param RouteDescriptor $right
     *
     * @return array<int, string>
     */
    private function buildNotes(array $left, array $right): array
    {
        $notes = [];

        if ($left['hasCondition'] || $right['hasCondition']) {
            $notes[] = 'Route conditions were not evaluated.';
        }

        if ($left['hostUnsupported'] || $right['hostUnsupported']) {
            $notes[] = 'Host requirements were not evaluated.';
        }

        return $notes;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMethods(array $methods): array
    {
        if ([] === $methods) {
            return [];
        }

        $normalized = [];
        foreach ($methods as $method) {
            if (!\is_string($method) || '' === $method) {
                continue;
            }
            $normalized[] = strtoupper($method);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSchemes(array $schemes): array
    {
        if ([] === $schemes) {
            return [];
        }

        $normalized = [];
        foreach ($schemes as $scheme) {
            if (!\is_string($scheme) || '' === $scheme) {
                continue;
            }
            $normalized[] = strtolower($scheme);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<int, string>
     */
    private function intersectMethods(array $left, array $right): array
    {
        if ([] === $left && [] === $right) {
            return [];
        }

        if ([] === $left) {
            return $right;
        }

        if ([] === $right) {
            return $left;
        }

        return array_values(array_intersect($left, $right));
    }

    /**
     * @return array<int, string>
     */
    private function intersectSchemes(array $left, array $right): array
    {
        if ([] === $left && [] === $right) {
            return [];
        }

        if ([] === $left) {
            return $right;
        }

        if ([] === $right) {
            return $left;
        }

        return array_values(array_intersect($left, $right));
    }

    private function methodsOverlap(array $left, array $right): bool
    {
        if ([] === $left || [] === $right) {
            return true;
        }

        return [] !== array_intersect($left, $right);
    }

    private function schemesOverlap(array $left, array $right): bool
    {
        if ([] === $left || [] === $right) {
            return true;
        }

        return [] !== array_intersect($left, $right);
    }

    private function prefixOverlaps(string $left, string $right): bool
    {
        if ('' === $left || '' === $right) {
            return true;
        }

        return str_starts_with($left, $right) || str_starts_with($right, $left);
    }

    /**
     * @return array<int, string>
     */
    private function extractStaticSegments(string $path): array
    {
        $trimmed = trim($path, '/');
        if ('' === $trimmed) {
            return [];
        }

        $segments = explode('/', $trimmed);
        $staticSegments = [];

        foreach ($segments as $segment) {
            if ('' === $segment || str_contains($segment, '{')) {
                continue;
            }
            $staticSegments[] = $segment;
        }

        return $staticSegments;
    }

    /**
     * @param callable(bool, bool): bool $acceptPredicate
     */
    private function findExample(Dfa $left, Dfa $right, callable $acceptPredicate): ?string
    {
        $startLeft = $left->startState;
        $startRight = $right->startState;
        $startKey = $this->pairKey($startLeft, $startRight);

        if ($acceptPredicate($left->getState($startLeft)->isAccepting, $right->getState($startRight)->isAccepting)) {
            return '';
        }

        /** @var \SplQueue<array{int, int, string}> $queue */
        $queue = new \SplQueue();
        $queue->enqueue([$startLeft, $startRight, $startKey]);

        /** @var array<string, bool> $visited */
        $visited = [$startKey => true];
        /** @var array<string, array{0:string, 1:int}|null> $previous */
        $previous = [$startKey => null];

        while (!$queue->isEmpty()) {
            [$leftStateId, $rightStateId, $currentKey] = $queue->dequeue();
            $leftState = $left->getState($leftStateId);
            $rightState = $right->getState($rightStateId);

            for ($char = CharSet::MIN_CODEPOINT; $char <= CharSet::MAX_CODEPOINT; $char++) {
                $nextLeft = $leftState->transitions[$char];
                $nextRight = $rightState->transitions[$char];
                $nextKey = $this->pairKey($nextLeft, $nextRight);

                if (isset($visited[$nextKey])) {
                    continue;
                }

                $visited[$nextKey] = true;
                $previous[$nextKey] = [$currentKey, $char];

                $nextLeftState = $left->getState($nextLeft);
                $nextRightState = $right->getState($nextRight);
                if ($acceptPredicate($nextLeftState->isAccepting, $nextRightState->isAccepting)) {
                    return $this->buildExample($nextKey, $previous);
                }

                $queue->enqueue([$nextLeft, $nextRight, $nextKey]);
            }
        }

        return null;
    }

    /**
     * @param array<string, array{0:string, 1:int}|null> $previous
     */
    private function buildExample(string $key, array $previous): string
    {
        $chars = '';
        $current = $key;
        while (null !== $previous[$current]) {
            [$prevKey, $char] = $previous[$current];
            $chars .= \chr($char);
            $current = $prevKey;
        }

        return \strrev($chars);
    }

    private function pairKey(int $leftState, int $rightState): string
    {
        return $leftState.':'.$rightState;
    }
}
