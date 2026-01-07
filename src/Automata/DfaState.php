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
 * Immutable DFA state.
 */
final readonly class DfaState
{
    /**
     * @param int        $id
     * @param array<int, int> $transitions
     * @param bool       $isAccepting
     */
    public function __construct(
        public int $id,
        public array $transitions,
        public bool $isAccepting,
    ) {}
}
