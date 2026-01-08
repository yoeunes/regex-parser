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

/**
 * Deterministic DFA minimizer delegating to a strategy implementation.
 */
final readonly class DfaMinimizer
{
    public function __construct(
        private ?MinimizationAlgorithmInterface $algorithm = null,
    ) {}

    public function minimize(Dfa $dfa): Dfa
    {
        $states = $dfa->states;

        if (\count($states) <= 1) {
            return $dfa;
        }

        $alphabet = $this->effectiveAlphabet($dfa);
        $algorithm = $this->algorithm ?? new HopcroftWorklist();

        return $algorithm->minimize($dfa, $alphabet);
    }

    /**
     * @return array<int>
     */
    private function effectiveAlphabet(Dfa $dfa): array
    {
        $alphabet = [];

        foreach ($dfa->states as $state) {
            foreach ($state->transitions as $symbol => $target) {
                $alphabet[(int) $symbol] = true;
            }
        }

        /** @var array<int> $result */
        $result = \array_keys($alphabet);
        \sort($result, \SORT_NUMERIC);

        return $result;
    }
}
