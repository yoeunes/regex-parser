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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Automata\Api\RegexLanguageSolver;
use RegexParser\Automata\Options\SolverOptions;

final class RegexLanguageSolverTest extends TestCase
{
    #[Test]
    public function test_facade_delegates_to_solver(): void
    {
        $solver = new RegexLanguageSolver();
        $options = new SolverOptions();

        $intersection = $solver->intersectionEmpty('/a/', '/b/', $options);
        $this->assertTrue($intersection->isEmpty);

        $subset = $solver->subsetOf('/a/', '/[ab]/', $options);
        $this->assertTrue($subset->isSubset);

        $equivalence = $solver->equivalent('/a+/', '/aa*/', $options);
        $this->assertTrue($equivalence->isEquivalent);
    }
}
