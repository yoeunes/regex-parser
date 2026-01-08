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

namespace RegexParser\Automata\Model;

/**
 * Immutable DFA state.
 */
final readonly class DfaState
{
    /**
     * @param array<int, int> $transitions
     * @param array<int, array{0:int, 1:int, 2:int}> $ranges
     */
    public function __construct(
        public int $id,
        public array $transitions,
        public bool $isAccepting,
        public array $ranges = [],
    ) {}

    public function transitionFor(int $codePoint): ?int
    {
        foreach ($this->ranges as [$start, $end, $target]) {
            if ($codePoint >= $start && $codePoint <= $end) {
                return $target;
            }
        }

        return $this->transitions[$codePoint] ?? null;
    }
}
