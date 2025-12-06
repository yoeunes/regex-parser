<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\Bridge\Symfony\Analyzer;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Analyzer\AnalysisIssue;
use RegexParser\Tests\Unit\Support\StubRegex;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteRequirementAnalyzerTest extends TestCase
{
    public function testAnalyzeReturnsIssuesForInvalidAndComplexPatterns(): void
    {
        $regex = \RegexParser\Regex::create();

        $analyzer = new RouteRequirementAnalyzer($regex, warningThreshold: 0, redosThreshold: 100000, ignoredPatterns: ['skip']);

        $routes = new RouteCollection();
        $routes->add('ignored_route', new Route('/a', [], ['id' => 'skip'])); // ignored by pattern
        $routes->add('invalid_route', new Route('/b', [], ['id' => '^($'])); // invalid pattern
        $routes->add('warning_route', new Route('/d', [], ['id' => '^baz$'])); // valid but exceeds warning threshold 0

        $issues = $analyzer->analyze($routes);

        self::assertCount(2, $issues);
        self::assertTrue($issues[0] instanceof AnalysisIssue && $issues[0]->isError);
        self::assertFalse($issues[1]->isError); // warning threshold exceeded
    }
}
