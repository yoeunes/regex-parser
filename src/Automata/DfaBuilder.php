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
 * Determinizes NFAs into DFAs via subset construction.
 */
final class DfaBuilder
{
    /**
     * @throws ComplexityException
     */
    public function determinize(Nfa $nfa, SolverOptions $options): Dfa
    {
        $startSet = $this->epsilonClosure([$nfa->startState], $nfa);

        /** @var array<string, int> $stateMap */
        $stateMap = [];
        /** @var array<int, array<int>> $stateSets */
        $stateSets = [];

        /** @var \SplQueue<int> $queue */
        $queue = new \SplQueue();

        $startKey = $this->setKey($startSet);
        $stateMap[$startKey] = 0;
        $stateSets[0] = $startSet;
        $queue->enqueue(0);

        /** @var array<int, array<int, int>> $transitions */
        $transitions = [];
        /** @var array<int, bool> $accepting */
        $accepting = [];

        while (!$queue->isEmpty()) {
            /** @var int $dfaId */
            $dfaId = $queue->dequeue();
            $currentSet = $stateSets[$dfaId];
            $accepting[$dfaId] = $this->isAccepting($currentSet, $nfa);

            /** @var array<int, int> $stateTransitions */
            $stateTransitions = [];
            for ($char = CharSet::MIN_CODEPOINT; $char <= CharSet::MAX_CODEPOINT; $char++) {
                $moveSet = $this->move($currentSet, $char, $nfa);
                $targetSet = $this->epsilonClosure($moveSet, $nfa);
                $targetKey = $this->setKey($targetSet);

                if (!isset($stateMap[$targetKey])) {
                    $newId = \count($stateMap);
                    if ($newId >= $options->maxDfaStates) {
                        throw new ComplexityException(
                            \sprintf('DFA state limit exceeded (%d).', $options->maxDfaStates),
                        );
                    }
                    $stateMap[$targetKey] = $newId;
                    $stateSets[$newId] = $targetSet;
                    $queue->enqueue($newId);
                }

                $stateTransitions[$char] = $stateMap[$targetKey];
            }

            $transitions[$dfaId] = $stateTransitions;
        }

        /** @var array<int, DfaState> $states */
        $states = [];
        foreach ($transitions as $stateId => $stateTransitions) {
            $states[$stateId] = new DfaState(
                $stateId,
                $stateTransitions,
                $accepting[$stateId] ?? false,
            );
        }

        return new Dfa(0, $states);
    }

    /**
     * @param array<int> $stateIds
     *
     * @return array<int>
     */
    private function epsilonClosure(array $stateIds, Nfa $nfa): array
    {
        /** @var \SplQueue<int> $queue */
        $queue = new \SplQueue();
        /** @var array<int, bool> $seen */
        $seen = [];

        foreach ($stateIds as $stateId) {
            $queue->enqueue($stateId);
            $seen[$stateId] = true;
        }

        while (!$queue->isEmpty()) {
            /** @var int $stateId */
            $stateId = $queue->dequeue();
            $state = $nfa->getState($stateId);
            foreach ($state->epsilonTransitions as $target) {
                if (!isset($seen[$target])) {
                    $seen[$target] = true;
                    $queue->enqueue($target);
                }
            }
        }

        /** @var array<int> $result */
        $result = \array_keys($seen);
        \sort($result, \SORT_NUMERIC);

        return $result;
    }

    /**
     * @param array<int> $stateIds
     */
    private function isAccepting(array $stateIds, Nfa $nfa): bool
    {
        foreach ($stateIds as $stateId) {
            /** @var int $stateId */
            if ($nfa->getState($stateId)->isAccepting) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int> $stateIds
     *
     * @return array<int>
     */
    private function move(array $stateIds, int $char, Nfa $nfa): array
    {
        $targets = [];
        foreach ($stateIds as $stateId) {
            /** @var int $stateId */
            $state = $nfa->getState($stateId);
            foreach ($state->transitions as $transition) {
                if ($transition->charSet->contains($char)) {
                    $targets[$transition->target] = true;
                }
            }
        }

        /** @var array<int> $result */
        $result = \array_keys($targets);
        \sort($result, \SORT_NUMERIC);

        return $result;
    }

    /**
     * @param array<int> $stateIds
     */
    private function setKey(array $stateIds): string
    {
        if ([] === $stateIds) {
            return 'empty';
        }

        return \implode(',', $stateIds);
    }
}
