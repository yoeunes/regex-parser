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
 * Deterministic DFA minimizer using partition refinement.
 */
final class DfaMinimizer
{
    public function minimize(Dfa $dfa): Dfa
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
                    $signature = $this->signature($states[$stateId], $stateToGroup);
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

        $newStates = [];
        foreach ($partitions as $newId => $group) {
            $representative = $states[$group[0]];
            $transitions = [];

            for ($char = CharSet::MIN_CODEPOINT; $char <= CharSet::MAX_CODEPOINT; $char++) {
                $target = $representative->transitions[$char];
                $transitions[$char] = $stateToGroup[$target];
            }

            $newStates[$newId] = new DfaState($newId, $transitions, $representative->isAccepting);
        }

        $startState = $stateToGroup[$dfa->startState];

        return new Dfa($startState, $newStates);
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
     */
    private function signature(DfaState $state, array $stateToGroup): string
    {
        $parts = [];

        for ($char = CharSet::MIN_CODEPOINT; $char <= CharSet::MAX_CODEPOINT; $char++) {
            $target = $state->transitions[$char] ?? null;
            $parts[] = null === $target ? 'x' : (string) $stateToGroup[$target];
        }

        return implode(',', $parts);
    }
}
