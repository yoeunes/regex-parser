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

namespace RegexParser\Automata\Options;

use RegexParser\Automata\Minimization\MinimizationAlgorithm;

/**
 * Configuration for automata-based regex comparisons.
 */
final readonly class SolverOptions
{
    /**
     * @param MatchMode             $matchMode               How to interpret matching semantics
     * @param int                   $maxNfaStates            Maximum allowed NFA states
     * @param int                   $maxDfaStates            Maximum allowed DFA states
     * @param bool                  $minimizeDfa             Whether to minimize DFAs after determinization
     * @param MinimizationAlgorithm $minimizationAlgorithm   Strategy used for DFA minimization
     * @param int|null              $maxTransitionsProcessed Hard limit on transition work (determinize/minimize)
     */
    public function __construct(
        public MatchMode $matchMode = MatchMode::FULL,
        public int $maxNfaStates = 5000,
        public int $maxDfaStates = 10000,
        public bool $minimizeDfa = true,
        public MinimizationAlgorithm $minimizationAlgorithm = MinimizationAlgorithm::HOPCROFT,
        public ?int $maxTransitionsProcessed = null,
    ) {}
}
