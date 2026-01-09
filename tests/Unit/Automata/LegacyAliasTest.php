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
use RegexParser\Automata\MatchMode;
use RegexParser\Automata\RegexSolver;
use RegexParser\Automata\RegularSubsetValidator;
use RegexParser\Automata\SolverOptions;

final class LegacyAliasTest extends TestCase
{
    #[Test]
    public function test_legacy_solver_alias_resolves_to_new_class(): void
    {
        $this->assertAlias(RegexSolver::class, \RegexParser\Automata\Solver\RegexSolver::class);
    }

    #[Test]
    public function test_legacy_solver_options_alias_resolves_to_new_class(): void
    {
        $this->assertAlias(SolverOptions::class, \RegexParser\Automata\Options\SolverOptions::class);
    }

    #[Test]
    public function test_legacy_match_mode_alias_resolves_to_new_enum(): void
    {
        $this->assertAlias(MatchMode::class, \RegexParser\Automata\Options\MatchMode::class);
    }

    #[Test]
    public function test_legacy_regular_subset_validator_alias_resolves_to_new_class(): void
    {
        $this->assertAlias(
            RegularSubsetValidator::class,
            \RegexParser\Automata\Transform\RegularSubsetValidator::class,
        );
    }

    /**
     * @param class-string $legacyClass
     * @param class-string $currentClass
     */
    private function assertAlias(string $legacyClass, string $currentClass): void
    {
        $legacyReflection = new \ReflectionClass($legacyClass);
        $currentReflection = new \ReflectionClass($currentClass);

        $this->assertSame($currentReflection->getName(), $legacyReflection->getName());
    }
}
