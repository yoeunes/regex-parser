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

namespace RegexParser\Automata\Builder;

use RegexParser\Automata\Alphabet\CharSet;
use RegexParser\Automata\Minimization\DfaMinimizer;
use RegexParser\Automata\Minimization\MinimizationAlgorithmFactory;
use RegexParser\Automata\Model\Dfa;
use RegexParser\Automata\Model\DfaState;
use RegexParser\Automata\Model\Nfa;
use RegexParser\Automata\Options\SolverOptions;
use RegexParser\Automata\Support\WorkBudget;
use RegexParser\Exception\ComplexityException;

/**
 * Determinizes NFAs into DFAs via subset construction.
 */
final readonly class DfaBuilder
{
    public function __construct(
        private ?DfaMinimizer $minimizer = null,
        private ?MinimizationAlgorithmFactory $minimizationFactory = null,
    ) {}

    /**
     * @throws ComplexityException
     */
    public function determinize(Nfa $nfa, SolverOptions $options): Dfa
    {
        $startSet = $this->epsilonClosure([$nfa->startState], $nfa);
        $alphabetRanges = $this->buildAlphabetRanges($nfa);
        $nfaTransitions = $this->countTransitions($nfa);
        $workBudget = null;
        if (null !== $options->maxTransitionsProcessed) {
            $workBudget = new WorkBudget(
                $options->maxTransitionsProcessed,
                'determinize',
                0,
                $nfaTransitions,
                \count($alphabetRanges),
            );
        }

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
        if (null !== $workBudget) {
            $workBudget->updateStats(\count($stateMap), $nfaTransitions, \count($alphabetRanges));
        }

        /** @var array<int, array{transitions: array<int, int>, ranges: array<int, array{0:int, 1:int, 2:int}>}> $transitions */
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
            /** @var array<int, array{0:int, 1:int, 2:int}> $stateRanges */
            $stateRanges = [];

            foreach ($alphabetRanges as [$start, $end]) {
                $symbol = $start;
                $moveSet = $this->move($currentSet, $symbol, $nfa, $workBudget);
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
                    if (null !== $workBudget) {
                        $workBudget->updateStats(\count($stateMap), $nfaTransitions, \count($alphabetRanges));
                    }
                }

                $targetId = $stateMap[$targetKey];
                if ($nfa->maxCodePoint <= CharSet::MAX_CODEPOINT) {
                    for ($char = $start; $char <= $end; $char++) {
                        $stateTransitions[$char] = $targetId;
                    }
                } else {
                    $stateTransitions[$symbol] = $targetId;
                }
                $stateRanges[] = [$start, $end, $targetId];
            }

            $transitions[$dfaId] = [
                'transitions' => $stateTransitions,
                'ranges' => $stateRanges,
            ];
        }

        /** @var array<int, DfaState> $states */
        $states = [];
        foreach ($transitions as $stateId => $stateTransitions) {
            $states[$stateId] = new DfaState(
                $stateId,
                $stateTransitions['transitions'],
                $accepting[$stateId] ?? false,
                $stateTransitions['ranges'],
            );
        }

        $dfa = new Dfa(0, $states, $alphabetRanges, $nfa->minCodePoint, $nfa->maxCodePoint);

        if (!$options->minimizeDfa) {
            return $dfa;
        }

        $minimizer = $this->minimizer;
        if (null === $minimizer) {
            $factory = $this->minimizationFactory ?? new MinimizationAlgorithmFactory();
            $algorithm = $factory->create($options->minimizationAlgorithm);
            $minimizer = new DfaMinimizer($algorithm);
        }

        return $minimizer->minimize($dfa, $options);
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
    private function move(array $stateIds, int $char, Nfa $nfa, ?WorkBudget $workBudget): array
    {
        $targets = [];
        foreach ($stateIds as $stateId) {
            $state = $nfa->getState($stateId);
            foreach ($state->transitions as $transition) {
                if (null !== $workBudget) {
                    $workBudget->consume();
                }
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

    /**
     * @return array<int, array{0:int, 1:int}>
     */
    private function buildAlphabetRanges(Nfa $nfa): array
    {
        $min = $nfa->minCodePoint;
        $max = $nfa->maxCodePoint;

        $boundaries = [
            $min => true,
            $max + 1 => true,
        ];

        foreach ($nfa->states as $state) {
            foreach ($state->transitions as $transition) {
                foreach ($transition->charSet->ranges() as [$start, $end]) {
                    $boundaries[$start] = true;
                    if ($end + 1 <= $max + 1) {
                        $boundaries[$end + 1] = true;
                    }
                }
            }
        }

        /** @var array<int> $points */
        $points = \array_keys($boundaries);
        \sort($points, \SORT_NUMERIC);

        $ranges = [];
        $count = \count($points);
        for ($i = 0; $i < $count - 1; $i++) {
            $start = $points[$i];
            $end = $points[$i + 1] - 1;

            if ($start > $max) {
                break;
            }

            if ($end < $min) {
                continue;
            }

            $ranges[] = [$start, \min($end, $max)];
        }

        if ([] === $ranges) {
            $ranges[] = [$min, $max];
        }

        return $ranges;
    }

    private function countTransitions(Nfa $nfa): int
    {
        $count = 0;
        foreach ($nfa->states as $state) {
            $count += \count($state->transitions);
        }

        return $count;
    }
}
