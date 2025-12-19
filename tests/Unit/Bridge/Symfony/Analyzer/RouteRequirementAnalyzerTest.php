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

namespace RegexParser\Tests\Unit\Bridge\Symfony\Analyzer;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\RegexPatternInspector;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Bridge\Symfony\Routing\RouteControllerFileResolver;
use RegexParser\Bridge\Symfony\Routing\RouteRequirementNormalizer;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteRequirementAnalyzerTest extends TestCase
{
    public function test_analyze_returns_issues_for_invalid_and_complex_patterns(): void
    {
        $regex = \RegexParser\Regex::create();

        $analyzer = $this->createAnalyzer($regex, warningThreshold: 0, redosThreshold: 'high', ignoredPatterns: ['skip']);

        $routes = new RouteCollection();
        $routes->add('ignored_route', new Route('/a', [], ['id' => 'skip'])); // ignored by pattern
        $routes->add('invalid_route', new Route('/b', [], ['id' => '^($'])); // invalid pattern
        $routes->add('warning_route', new Route('/d', [], ['id' => '^baz$'])); // valid but exceeds warning threshold 0

        $issues = $analyzer->analyze($routes);

        $this->assertCount(2, $issues);
        $this->assertTrue($issues[0]->isError);
        $this->assertFalse($issues[1]->isError); // warning threshold exceeded
    }

    public function test_analyze_ignores_configured_patterns(): void
    {
        $regex = \RegexParser\Regex::create();
        $analyzer = $this->createAnalyzer($regex, warningThreshold: 10, redosThreshold: 'high', ignoredPatterns: ['foo']);

        $routes = new RouteCollection();
        $routes->add('ignored', new Route('/a', [], ['id' => 'foo']));

        $this->assertSame([], $analyzer->analyze($routes));
    }

    public function test_analyze_skips_trivially_safe_patterns(): void
    {
        $regex = \RegexParser\Regex::create();
        $analyzer = $this->createAnalyzer($regex, warningThreshold: 10, redosThreshold: 'high');

        $routes = new RouteCollection();
        $routes->add('safe', new Route('/a', [], ['id' => 'foo|bar']));

        $this->assertSame([], $analyzer->analyze($routes));
    }

    /**
     * @param list<string> $ignoredPatterns
     */
    private function createAnalyzer(
        \RegexParser\Regex $regex,
        int $warningThreshold,
        string $redosThreshold,
        array $ignoredPatterns = [],
    ): RouteRequirementAnalyzer {
        return new RouteRequirementAnalyzer(
            $regex,
            new RegexPatternInspector(),
            new RouteRequirementNormalizer(),
            new RouteControllerFileResolver(),
            $warningThreshold,
            $redosThreshold,
            $ignoredPatterns,
        );
    }
}
