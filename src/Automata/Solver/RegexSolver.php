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

use RegexParser\Automata\Alphabet\CharSet;
use RegexParser\Automata\Builder\DfaBuilder;
use RegexParser\Automata\Model\Dfa;
use RegexParser\Automata\Options\SolverOptions;
use RegexParser\Automata\Transform\AstToNfaTransformer;
use RegexParser\Automata\Transform\RegularSubsetValidator;
use RegexParser\Exception\ComplexityException;
use RegexParser\Regex;

/**
 * Automata-based solver for regex set operations.
 */
final readonly class RegexSolver implements RegexSolverInterface
{
    public function __construct(
        private ?Regex $regex = null,
        private ?RegularSubsetValidator $validator = null,
        private ?DfaBuilder $dfaBuilder = null,
    ) {}

    /**
     * @throws ComplexityException
     */
    public function intersection(string $left, string $right, ?SolverOptions $options = null): IntersectionResult
    {
        $options ??= new SolverOptions();
        [$leftDfa, $rightDfa] = $this->buildDfas($left, $right, $options);

        $example = $this->findExample(
            $leftDfa,
            $rightDfa,
            static fn (bool $leftAccept, bool $rightAccept): bool => $leftAccept && $rightAccept,
        );

        return new IntersectionResult(null === $example, $example);
    }

    /**
     * @throws ComplexityException
     */
    public function subsetOf(string $left, string $right, ?SolverOptions $options = null): SubsetResult
    {
        $options ??= new SolverOptions();
        [$leftDfa, $rightDfa] = $this->buildDfas($left, $right, $options);

        $example = $this->findExample(
            $leftDfa,
            $rightDfa,
            static fn (bool $leftAccept, bool $rightAccept): bool => $leftAccept && !$rightAccept,
        );

        return new SubsetResult(null === $example, $example);
    }

    /**
     * @throws ComplexityException
     */
    public function equivalent(string $left, string $right, ?SolverOptions $options = null): EquivalenceResult
    {
        $options ??= new SolverOptions();
        [$leftDfa, $rightDfa] = $this->buildDfas($left, $right, $options);

        $leftOnlyExample = $this->findExample(
            $leftDfa,
            $rightDfa,
            static fn (bool $leftAccept, bool $rightAccept): bool => $leftAccept && !$rightAccept,
        );

        $rightOnlyExample = $this->findExample(
            $leftDfa,
            $rightDfa,
            static fn (bool $leftAccept, bool $rightAccept): bool => !$leftAccept && $rightAccept,
        );

        return new EquivalenceResult(null === $leftOnlyExample && null === $rightOnlyExample, $leftOnlyExample, $rightOnlyExample);
    }

    private function parser(): Regex
    {
        return $this->regex ?? Regex::create();
    }

    /**
     * @throws ComplexityException
     *
     * @return array{0:Dfa, 1:Dfa}
     */
    private function buildDfas(string $left, string $right, SolverOptions $options): array
    {
        return [
            $this->buildDfa($left, $options),
            $this->buildDfa($right, $options),
        ];
    }

    /**
     * @throws ComplexityException
     */
    private function buildDfa(string $pattern, SolverOptions $options): Dfa
    {
        $ast = $this->parser()->parse($pattern);
        $validator = $this->validator ?? new RegularSubsetValidator();
        $validator->assertSupported($ast, $pattern, $options);

        $transformer = new AstToNfaTransformer($pattern);
        $nfa = $transformer->transform($ast, $options);

        $dfaBuilder = $this->dfaBuilder ?? new DfaBuilder();

        return $dfaBuilder->determinize($nfa, $options);
    }

    /**
     * @param callable(bool, bool): bool $acceptPredicate
     */
    private function findExample(Dfa $left, Dfa $right, callable $acceptPredicate): ?string
    {
        $startLeft = $left->startState;
        $startRight = $right->startState;
        $startKey = $this->pairKey($startLeft, $startRight);

        if ($acceptPredicate($left->getState($startLeft)->isAccepting, $right->getState($startRight)->isAccepting)) {
            return '';
        }

        /** @var \SplQueue<array{int, int, string}> $queue */
        $queue = new \SplQueue();
        $queue->enqueue([$startLeft, $startRight, $startKey]);

        /** @var array<string, bool> $visited */
        $visited = [$startKey => true];
        /** @var array<string, array{0:string, 1:int}|null> $previous */
        $previous = [$startKey => null];

        while (!$queue->isEmpty()) {
            $item = $queue->dequeue();
            [$leftStateId, $rightStateId, $currentKey] = $item;
            $leftState = $left->getState($leftStateId);
            $rightState = $right->getState($rightStateId);

            for ($char = CharSet::MIN_CODEPOINT; $char <= CharSet::MAX_CODEPOINT; $char++) {
                $nextLeft = $leftState->transitions[$char];
                $nextRight = $rightState->transitions[$char];
                $nextKey = $this->pairKey($nextLeft, $nextRight);

                if (isset($visited[$nextKey])) {
                    continue;
                }

                $visited[$nextKey] = true;
                $previous[$nextKey] = [$currentKey, $char];

                $nextLeftState = $left->getState($nextLeft);
                $nextRightState = $right->getState($nextRight);
                if ($acceptPredicate($nextLeftState->isAccepting, $nextRightState->isAccepting)) {
                    return $this->buildExample($nextKey, $previous);
                }

                $queue->enqueue([$nextLeft, $nextRight, $nextKey]);
            }
        }

        return null;
    }

    /**
     * @param array<string, array{0:string, 1:int}|null> $previous
     */
    private function buildExample(string $key, array $previous): string
    {
        $chars = '';
        $current = $key;
        while (null !== $previous[$current]) {
            [$prevKey, $char] = $previous[$current];
            $chars .= \chr($char);
            $current = $prevKey;
        }

        return \strrev($chars);
    }

    private function pairKey(int $leftState, int $rightState): string
    {
        return $leftState.':'.$rightState;
    }
}
