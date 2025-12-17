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

namespace RegexParser\Tests\Bridge\Symfony\Analyzer;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Regex;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteRequirementAnalyzerTest extends TestCase
{
    public function test_valid_requirement_produces_no_issues(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 100, 'high');

        $routes = new RouteCollection();
        $routes->add('home', new Route('/home', [], ['slug' => '[a-z]+']));

        $this->assertSame([], $analyzer->analyze($routes));
    }

    public function test_invalid_requirement_is_reported(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 100, 'high');

        $routes = new RouteCollection();
        $routes->add('broken', new Route('/broken', [], ['id' => '(']));

        $issues = $analyzer->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertTrue($issues[0]->isError);
        $this->assertStringContainsString('broken', $issues[0]->message);
    }

    public function test_warning_threshold_is_applied(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 'high');

        $routes = new RouteCollection();
        $routes->add('warn', new Route('/warn', [], ['name' => '[a-z]+']));

        $issues = $analyzer->analyze($routes);

        $this->assertCount(1, $issues);
        $this->assertFalse($issues[0]->isError);
        $this->assertStringContainsString('warn', $issues[0]->message);
    }

    public function test_literal_alternations_are_skipped(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 'high');

        $routes = new RouteCollection();
        $routes->add('locale', new Route('/{_locale}', [], ['_locale' => 'en|fr|de']));

        $this->assertSame([], $analyzer->analyze($routes));
    }

    public function test_slug_pattern_is_not_flagged(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 0, 'high', ['[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*']);

        $routes = new RouteCollection();
        $routes->add('slug', new Route('/{slug}', [], ['slug' => '^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$']));

        $this->assertSame([], $analyzer->analyze($routes));
    }
}
