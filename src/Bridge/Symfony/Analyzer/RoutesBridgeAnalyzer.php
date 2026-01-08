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

use RegexParser\Bridge\Symfony\Routing\RouteConflictAnalyzer;
use RegexParser\Bridge\Symfony\Routing\RouteConflictReport;
use RegexParser\Bridge\Symfony\Routing\RouteConflictSuggestionBuilder;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 *
 * @phpstan-import-type RouteConflict from RouteConflictReport
 * @phpstan-import-type RouteDescriptor from RouteConflictReport
 */
final readonly class RoutesBridgeAnalyzer implements BridgeAnalyzerInterface
{
    private const ID = 'routes';
    private const PRIORITY = 10;
    private const ARROW_LABEL = "\u{21B3}";

    public function __construct(
        private RouteConflictAnalyzer $analyzer,
        private RouteConflictSuggestionBuilder $suggestionBuilder = new RouteConflictSuggestionBuilder(),
        private ?RouterInterface $router = null,
    ) {}

    public function getId(): string
    {
        return self::ID;
    }

    public function getLabel(): string
    {
        return 'Routes';
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function analyze(BridgeRunContext $context): array
    {
        if (null === $this->router) {
            return [
                new BridgeReportSection(
                    self::ID,
                    'Routes',
                    summary: [
                        new BridgeNotice(BridgeSeverity::WARN, 'Router service is not available.'),
                    ],
                ),
            ];
        }

        $collection = $this->router->getRouteCollection();
        $routes = $collection->all();
        $routeCount = \count($routes);

        if (0 === $routeCount) {
            return [
                new BridgeReportSection(
                    self::ID,
                    'Routes',
                    meta: ['Routes' => 0],
                    summary: [
                        new BridgeNotice(BridgeSeverity::PASS, 'No routes found.'),
                    ],
                ),
            ];
        }

        $report = $this->analyzer->analyze($collection, $context->includeOverlaps);

        $meta = [
            'Routes' => $report->stats['routes'],
            'Mode' => $context->includeOverlaps ? 'Shadowed + overlaps' : 'Shadowed only',
            'Shadowed' => $report->stats['shadowed'],
            'Overlaps' => $report->stats['overlaps'],
        ];

        $warnings = $this->buildWarnings($report);
        $summary = $this->buildSummary($report, $context->includeOverlaps);

        $issues = [];
        foreach ($report->conflicts as $conflict) {
            $issues[] = $this->buildIssue($conflict);
        }

        $suggestions = [];
        if ([] !== $report->conflicts) {
            $suggestions = $this->suggestionBuilder->collect($report->conflicts);
        }

        return [
            new BridgeReportSection(
                self::ID,
                'Routes',
                $meta,
                $summary,
                $warnings,
                $issues,
                $suggestions,
            ),
        ];
    }

    /**
     * @return array<int, BridgeNotice>
     */
    private function buildWarnings(RouteConflictReport $report): array
    {
        $warnings = [];

        if ([] !== $report->skippedRoutes) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf('%d routes skipped due to unsupported regex features.', \count($report->skippedRoutes)),
            );
        }

        if ([] !== $report->routesWithConditions) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf(
                    '%d routes use conditions; conditions are not evaluated during analysis.',
                    \count(array_unique($report->routesWithConditions)),
                ),
            );
        }

        if ([] !== $report->routesWithUnsupportedHosts) {
            $warnings[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf(
                    '%d routes use host requirements that could not be analyzed.',
                    \count(array_unique($report->routesWithUnsupportedHosts)),
                ),
            );
        }

        return $warnings;
    }

    /**
     * @return array<int, BridgeNotice>
     */
    private function buildSummary(RouteConflictReport $report, bool $includeOverlaps): array
    {
        $summary = [];
        $shadowed = $report->stats['shadowed'];
        $overlaps = $report->stats['overlaps'];

        if (0 === $shadowed && 0 === $overlaps) {
            $summary[] = new BridgeNotice(BridgeSeverity::PASS, 'No route conflicts detected.');

            return $summary;
        }

        if ($shadowed > 0) {
            $summary[] = new BridgeNotice(
                BridgeSeverity::FAIL,
                \sprintf('%d shadowed routes detected.', $shadowed),
            );
        }

        if ($overlaps > 0) {
            $suffix = $includeOverlaps ? 'Listed below.' : 'Use --show-overlaps to include them.';
            $summary[] = new BridgeNotice(
                BridgeSeverity::WARN,
                \sprintf('%d overlapping routes detected. %s', $overlaps, $suffix),
            );
        }

        return $summary;
    }

    /**
     * @phpstan-param RouteConflict $conflict
     */
    private function buildIssue(array $conflict): BridgeIssue
    {
        $route = $conflict['route'];
        $other = $conflict['conflict'];
        $type = $conflict['type'];

        $severity = 'shadowed' === $type ? BridgeSeverity::FAIL : BridgeSeverity::WARN;
        $title = \sprintf(
            '%s (#%d) %s %s (#%d)',
            $route['name'],
            $route['index'],
            self::ARROW_LABEL,
            $other['name'],
            $other['index'],
        );

        $details = [
            new BridgeIssueDetail('Route', $route['path']),
            new BridgeIssueDetail('Conflict', $other['path']),
            new BridgeIssueDetail('Scope', $this->formatScope($conflict['methods'], $conflict['schemes'])),
        ];

        if (null !== $conflict['example']) {
            $details[] = new BridgeIssueDetail('Example', $conflict['example'], 'example');
        }

        return new BridgeIssue(
            $type,
            $severity,
            $title,
            $details,
            $conflict['notes'],
        );
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, string> $schemes
     */
    private function formatScope(array $methods, array $schemes): string
    {
        if ([] === $methods && [] === $schemes) {
            return 'any';
        }

        $parts = [];
        if ([] !== $methods) {
            $parts[] = implode('|', $methods);
        }
        if ([] !== $schemes) {
            $parts[] = implode('|', $schemes);
        }

        return implode(' • ', $parts);
    }
}
