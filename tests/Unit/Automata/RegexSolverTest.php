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

namespace RegexParser\Tests\Unit\Automata;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Automata\MatchMode;
use RegexParser\Automata\RegexSolver;
use RegexParser\Automata\SolverOptions;

final class RegexSolverTest extends TestCase
{
    #[Test]
    #[DataProvider('provideIntersectionCases')]
    public function test_intersection_results(
        string $left,
        string $right,
        bool $expectedEmpty,
        ?string $expectedExample,
    ): void {
        $solver = new RegexSolver();
        $result = $solver->intersection($left, $right, $this->fullMatchOptions());

        $this->assertSame($expectedEmpty, $result->isEmpty);
        $this->assertSame($expectedExample, $result->example);
    }

    #[Test]
    #[DataProvider('provideSubsetCases')]
    public function test_subset_results(
        string $left,
        string $right,
        bool $expectedSubset,
        bool $expectsCounterExample,
    ): void {
        $solver = new RegexSolver();
        $result = $solver->subsetOf($left, $right, $this->fullMatchOptions());

        $this->assertSame($expectedSubset, $result->isSubset);

        if ($expectsCounterExample) {
            $this->assertNotNull($result->counterExample);
        } else {
            $this->assertNull($result->counterExample);
        }
    }

    #[Test]
    public function test_route_shadowing_is_detected(): void
    {
        $solver = new RegexSolver();
        $result = $solver->subsetOf('/edit/', '/[a-z]+/', $this->fullMatchOptions());

        $this->assertTrue($result->isSubset);
    }

    #[Test]
    public function test_equivalence_of_refactorings_is_detected(): void
    {
        $solver = new RegexSolver();
        $result = $solver->equivalent('/(a|b)c/', '/ac|bc/', $this->fullMatchOptions());

        $this->assertTrue($result->isEquivalent);
        $this->assertNull($result->leftOnlyExample);
        $this->assertNull($result->rightOnlyExample);
    }

    #[Test]
    public function test_non_equivalence_returns_counter_example(): void
    {
        $solver = new RegexSolver();
        $result = $solver->equivalent('/a*/', '/a+/', $this->fullMatchOptions());

        $this->assertFalse($result->isEquivalent);
        $this->assertSame('', $result->leftOnlyExample);
        $this->assertNull($result->rightOnlyExample);
    }

    #[Test]
    public function test_full_match_semantics_treat_anchors_as_redundant(): void
    {
        $solver = new RegexSolver();
        $result = $solver->equivalent('/^foo$/', '/foo/', $this->fullMatchOptions());

        $this->assertTrue($result->isEquivalent);
    }

    public static function provideIntersectionCases(): \Generator
    {
        yield 'disjoint char classes' => ['/[a-z]/', '/[0-9]/', true, null];
        yield 'literal within word class' => ['/\\w+/', '/abc/', false, 'abc'];
    }

    public static function provideSubsetCases(): \Generator
    {
        yield 'letters are subset of alnum' => ['/[a-z]+/', '/[a-z0-9]+/', true, false];
        yield 'alnum is not subset of letters' => ['/[a-z0-9]+/', '/[a-z]+/', false, true];
    }

    private function fullMatchOptions(): SolverOptions
    {
        return new SolverOptions(matchMode: MatchMode::FULL);
    }
}
