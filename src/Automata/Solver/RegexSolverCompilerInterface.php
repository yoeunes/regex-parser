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

namespace RegexParser\Automata\Solver;

use RegexParser\Automata\Model\Dfa;
use RegexParser\Automata\Options\SolverOptions;
use RegexParser\Exception\ComplexityException;

/**
 * Optional interface for solvers that can compile and cache DFAs.
 */
interface RegexSolverCompilerInterface
{
    /**
     * @throws ComplexityException
     */
    public function compile(string $pattern, ?SolverOptions $options = null): Dfa;
}
