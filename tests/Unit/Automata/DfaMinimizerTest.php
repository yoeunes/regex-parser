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

namespace RegexParser\Tests\Unit\Automata;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\Automata\CharSet;
use RegexParser\Automata\Dfa;
use RegexParser\Automata\DfaMinimizer;
use RegexParser\Automata\DfaState;

final class DfaMinimizerTest extends TestCase
{
    #[Test]
    public function test_minimize_merges_equivalent_states(): void
    {
        $states = [
            0 => new DfaState(0, $this->transitions(3, ['a' => 1, 'b' => 2]), false),
            1 => new DfaState(1, $this->transitions(3), true),
            2 => new DfaState(2, $this->transitions(3), true),
            3 => new DfaState(3, $this->transitions(3), false),
        ];

        $dfa = new Dfa(0, $states);
        $minimized = (new DfaMinimizer())->minimize($dfa);

        $this->assertCount(3, $minimized->states);

        foreach (['', 'a', 'b', 'ab', 'ba', 'aa', 'bb', 'c'] as $input) {
            $this->assertSame($this->accepts($dfa, $input), $this->accepts($minimized, $input));
        }
    }

    private function accepts(Dfa $dfa, string $input): bool
    {
        $stateId = $dfa->startState;
        $length = \strlen($input);

        for ($index = 0; $index < $length; $index++) {
            $stateId = $dfa->getState($stateId)->transitions[\ord($input[$index])];
        }

        return $dfa->getState($stateId)->isAccepting;
    }

    /**
     * @param array<string, int> $overrides
     *
     * @return array<int, int>
     */
    private function transitions(int $defaultTarget, array $overrides = []): array
    {
        $count = CharSet::MAX_CODEPOINT - CharSet::MIN_CODEPOINT + 1;
        $transitions = \array_fill(CharSet::MIN_CODEPOINT, $count, $defaultTarget);

        foreach ($overrides as $char => $target) {
            $transitions[\ord($char)] = $target;
        }

        return $transitions;
    }
}
