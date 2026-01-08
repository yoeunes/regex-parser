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
 * Moore's partition refinement minimization algorithm.
 */
final class MoorePartitionRefinement implements MinimizationAlgorithmInterface
{
    /**
     * @param array<int> $alphabet
     */
    public function minimize(Dfa $dfa, array $alphabet): Dfa
    {
        $states = $dfa->states;

        if (\count($states) <= 1) {
            return $dfa;
        }

        $accepting = [];
        $nonAccepting = [];

        foreach ($states as $stateId => $state) {
            if ($state->isAccepting) {
                $accepting[] = $stateId;

                continue;
            }

            $nonAccepting[] = $stateId;
        }

        /** @var array<int, array<int, int>> $partitions */
        $partitions = [];
        if ([] !== $nonAccepting) {
            $partitions[] = $nonAccepting;
        }
        if ([] !== $accepting) {
            $partitions[] = $accepting;
        }

        $stateToGroup = $this->buildStateToGroup($partitions);

        $changed = true;
        while ($changed) {
            $changed = false;
            $newPartitions = [];

            foreach ($partitions as $group) {
                $buckets = [];
                foreach ($group as $stateId) {
                    $signature = $this->signature($states[$stateId], $stateToGroup, $alphabet);
                    $buckets[$signature][] = $stateId;
                }

                if (\count($buckets) > 1) {
                    $changed = true;
                }

                foreach ($buckets as $bucket) {
                    $newPartitions[] = $bucket;
                }
            }

            $partitions = $newPartitions;
            $stateToGroup = $this->buildStateToGroup($partitions);
        }

        return $this->buildMinimizedDfa($dfa, $partitions, $stateToGroup);
    }

    /**
     * @param array<int, array<int, int>> $partitions
     *
     * @return array<int, int>
     */
    private function buildStateToGroup(array $partitions): array
    {
        $lookup = [];

        foreach ($partitions as $groupId => $states) {
            foreach ($states as $stateId) {
                $lookup[$stateId] = $groupId;
            }
        }

        return $lookup;
    }

    /**
     * @param array<int, int> $stateToGroup
     * @param array<int>      $alphabet
     */
    private function signature(DfaState $state, array $stateToGroup, array $alphabet): string
    {
        $parts = [];

        foreach ($alphabet as $symbol) {
            $target = $state->transitions[$symbol] ?? null;
            $parts[] = null === $target ? 'x' : (string) $stateToGroup[$target];
        }

        return implode(',', $parts);
    }

    /**
     * @param array<int, array<int, int>> $partitions
     * @param array<int, int>             $stateToGroup
     */
    private function buildMinimizedDfa(Dfa $dfa, array $partitions, array $stateToGroup): Dfa
    {
        $newStates = [];

        foreach ($partitions as $newId => $group) {
            $representative = $dfa->states[$group[0]];
            $transitions = [];

            foreach ($representative->transitions as $symbol => $target) {
                $transitions[(int) $symbol] = $stateToGroup[$target];
            }

            $newStates[$newId] = new DfaState($newId, $transitions, $representative->isAccepting);
        }

        $startState = $stateToGroup[$dfa->startState];

        return new Dfa($startState, $newStates);
    }
}
