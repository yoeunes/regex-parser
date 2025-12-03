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
use RegexParser\Bridge\Symfony\Analyzer\RouteRequirementAnalyzer;
use RegexParser\Regex;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteRequirementAnalyzerTest extends TestCase
{
    public function testAnalyzeIgnoresValidRequirements(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 100, 200);

        $routes = new RouteCollection();
        $routes->add('home', new Route('/home', [], ['slug' => '[a-z]+']));

        self::assertSame([], $analyzer->analyze($routes));
    }

    public function testAnalyzeReportsInvalidRequirement(): void
    {
        $analyzer = new RouteRequirementAnalyzer(Regex::create(), 100, 200);

        $routes = new RouteCollection();
        $routes->add('broken', new Route('/broken', [], ['id' => '(']));

        $issues = $analyzer->analyze($routes);

        self::assertCount(1, $issues);
        self::assertTrue($issues[0]->isError);
        self::assertStringContainsString('broken', $issues[0]->message);
        self::assertStringContainsString('id', $issues[0]->message);
    }
}
