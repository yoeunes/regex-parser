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

namespace RegexParser\Automata;

use RegexParser\Exception\ComplexityException;

/**
 * Defines automata-based comparison operations for regexes.
 */
interface RegexSolverInterface
{
    /**
     * Compute whether two regexes intersect.
     *
     * @param string             $left    Left regex pattern
     * @param string             $right   Right regex pattern
     * @param SolverOptions|null $options Comparison options
     *
     * @throws ComplexityException When the regexes exceed the supported subset
     */
    public function intersection(string $left, string $right, ?SolverOptions $options = null): IntersectionResult;

    /**
     * Determine if left regex language is a subset of the right.
     *
     * @param string             $left    Left regex pattern
     * @param string             $right   Right regex pattern
     * @param SolverOptions|null $options Comparison options
     *
     * @throws ComplexityException When the regexes exceed the supported subset
     */
    public function subsetOf(string $left, string $right, ?SolverOptions $options = null): SubsetResult;

    /**
     * Determine if two regexes are equivalent.
     *
     * @param string             $left    Left regex pattern
     * @param string             $right   Right regex pattern
     * @param SolverOptions|null $options Comparison options
     *
     * @throws ComplexityException When the regexes exceed the supported subset
     */
    public function equivalent(string $left, string $right, ?SolverOptions $options = null): EquivalenceResult;
}
