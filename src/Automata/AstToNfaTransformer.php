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
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\ClassOperationType;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;

/**
 * Builds an NFA from a regex AST using Thompson construction.
 */
final class AstToNfaTransformer implements AstToNfaTransformerInterface
{
    private NfaBuilder $builder;

    private bool $caseInsensitive = false;

    private bool $dotAll = false;

    public function __construct(
        private readonly string $pattern,
    ) {}

    /**
     * @throws ComplexityException
     */
    public function transform(RegexNode $regex, SolverOptions $options): Nfa
    {
        $this->builder = new NfaBuilder($options->maxNfaStates);
        $this->caseInsensitive = \str_contains($regex->flags, 'i');
        $this->dotAll = \str_contains($regex->flags, 's');

        $fragment = $this->buildNode($regex->pattern, $options);

        return $this->builder->build($fragment);
    }

    /**
     * @throws ComplexityException
     */
    private function buildNode(NodeInterface $node, SolverOptions $options): NfaFragment
    {
        if ($node instanceof SequenceNode) {
            return $this->buildSequence($node, $options);
        }

        if ($node instanceof AlternationNode) {
            return $this->buildAlternation($node, $options);
        }

        if ($node instanceof GroupNode) {
            return $this->buildNode($node->child, $options);
        }

        if ($node instanceof QuantifierNode) {
            return $this->buildQuantifier($node, $options);
        }

        if ($node instanceof LiteralNode) {
            return $this->buildLiteral($node);
        }

        if ($node instanceof CharLiteralNode) {
            return $this->buildCharFromCodePoint($node->codePoint, $node->getStartPosition());
        }

        if ($node instanceof ControlCharNode) {
            return $this->buildCharFromCodePoint($node->codePoint, $node->getStartPosition());
        }

        if ($node instanceof CharTypeNode) {
            return $this->buildCharType($node);
        }

        if ($node instanceof CharClassNode) {
            return $this->buildCharClass($node);
        }

        if ($node instanceof RangeNode) {
            return $this->buildRange($node);
        }

        if ($node instanceof AnchorNode) {
            if (MatchMode::FULL !== $options->matchMode) {
                throw new ComplexityException('Anchors are not supported in partial match mode.');
            }

            return $this->epsilonFragment();
        }

        if ($node instanceof DotNode) {
            return $this->buildDot();
        }

        throw new ComplexityException('Unsupported regex node in automata conversion.', $node->getStartPosition(), $this->pattern);
    }

    /**
     * @throws ComplexityException
     */
    private function buildSequence(SequenceNode $node, SolverOptions $options): NfaFragment
    {
        if ([] === $node->children) {
            return $this->epsilonFragment();
        }

        $fragments = [];
        foreach ($node->children as $child) {
            $fragments[] = $this->buildNode($child, $options);
        }

        $current = \array_shift($fragments);
        foreach ($fragments as $fragment) {
            $current = $this->concatenate($current, $fragment);
        }

        return $current;
    }

    /**
     * @throws ComplexityException
     */
    private function buildAlternation(AlternationNode $node, SolverOptions $options): NfaFragment
    {
        $start = $this->builder->createState();
        $end = $this->builder->createState();

        foreach ($node->alternatives as $alternative) {
            $fragment = $this->buildNode($alternative, $options);
            $this->builder->addEpsilon($start, $fragment->startState);
            foreach ($fragment->acceptStates as $acceptState) {
                $this->builder->addEpsilon($acceptState, $end);
            }
        }

        return new NfaFragment($start, [$end]);
    }

    /**
     * @throws ComplexityException
     */
    private function buildQuantifier(QuantifierNode $node, SolverOptions $options): NfaFragment
    {
        [$min, $max] = $this->parseQuantifierRange($node->quantifier);

        if (0 === $min && 0 === $max) {
            return $this->epsilonFragment();
        }

        if (null === $max) {
            if (0 === $min) {
                return $this->buildStar($node->node, $options);
            }

            $fragment = $this->repeatNode($node->node, $options, $min);
            $star = $this->buildStar($node->node, $options);

            return $this->concatenate($fragment, $star);
        }

        return $this->buildBoundedRepeat($node->node, $options, $min, $max);
    }

    /**
     * @throws ComplexityException
     */
    private function buildLiteral(LiteralNode $node): NfaFragment
    {
        if ('' === $node->value) {
            return $this->epsilonFragment();
        }

        $chars = \str_split($node->value);
        $start = $this->builder->createState();
        $current = $start;

        foreach ($chars as $char) {
            $next = $this->builder->createState();
            $charSet = $this->applyCaseInsensitive(CharSet::fromChar($char));
            $this->builder->addTransition($current, $charSet, $next);
            $current = $next;
        }

        return new NfaFragment($start, [$current]);
    }

    /**
     * @throws ComplexityException
     */
    private function buildCharFromCodePoint(int $codePoint, int $position): NfaFragment
    {
        if ($codePoint < CharSet::MIN_CODEPOINT || $codePoint > CharSet::MAX_CODEPOINT) {
            throw new ComplexityException('Character outside supported alphabet.', $position, $this->pattern);
        }

        $start = $this->builder->createState();
        $end = $this->builder->createState();
        $charSet = $this->applyCaseInsensitive(CharSet::fromCodePoint($codePoint));
        $this->builder->addTransition($start, $charSet, $end);

        return new NfaFragment($start, [$end]);
    }

    /**
     * @throws ComplexityException
     */
    private function buildCharType(CharTypeNode $node): NfaFragment
    {
        $charSet = match ($node->value) {
            'd' => CharSet::fromRange(\ord('0'), \ord('9')),
            'D' => CharSet::fromRange(\ord('0'), \ord('9'))->complement(),
            'w' => $this->wordCharSet(),
            'W' => $this->wordCharSet()->complement(),
            's' => $this->spaceCharSet(),
            'S' => $this->spaceCharSet()->complement(),
            default => throw new ComplexityException('Unsupported character type: '.$node->value.'.', $node->getStartPosition(), $this->pattern),
        };

        $start = $this->builder->createState();
        $end = $this->builder->createState();
        $this->builder->addTransition($start, $this->applyCaseInsensitive($charSet), $end);

        return new NfaFragment($start, [$end]);
    }

    /**
     * @throws ComplexityException
     */
    private function buildCharClass(CharClassNode $node): NfaFragment
    {
        $charSet = $this->applyCaseInsensitive($this->buildCharClassExpression($node->expression));
        if ($node->isNegated) {
            $charSet = $charSet->complement();
        }

        $start = $this->builder->createState();
        $end = $this->builder->createState();
        $this->builder->addTransition($start, $charSet, $end);

        return new NfaFragment($start, [$end]);
    }

    /**
     * @throws ComplexityException
     */
    private function buildRange(RangeNode $node): NfaFragment
    {
        $startCode = $this->extractCodePoint($node->start);
        $endCode = $this->extractCodePoint($node->end);

        $charSet = CharSet::fromRange($startCode, $endCode);
        $charSet = $this->applyCaseInsensitive($charSet);

        $start = $this->builder->createState();
        $end = $this->builder->createState();
        $this->builder->addTransition($start, $charSet, $end);

        return new NfaFragment($start, [$end]);
    }

    private function buildDot(): NfaFragment
    {
        $charSet = CharSet::full();
        if (!$this->dotAll) {
            $charSet = $charSet->subtract(CharSet::fromCodePoint(\ord("\n")));
        }

        $start = $this->builder->createState();
        $end = $this->builder->createState();
        $this->builder->addTransition($start, $charSet, $end);

        return new NfaFragment($start, [$end]);
    }

    /**
     * @throws ComplexityException
     */
    private function buildCharClassExpression(NodeInterface $node): CharSet
    {
        if ($node instanceof AlternationNode) {
            $set = CharSet::empty();
            foreach ($node->alternatives as $alternative) {
                $set = $set->union($this->buildCharClassExpression($alternative));
            }

            return $set;
        }

        if ($node instanceof RangeNode) {
            $startCode = $this->extractCodePoint($node->start);
            $endCode = $this->extractCodePoint($node->end);

            return CharSet::fromRange($startCode, $endCode);
        }

        if ($node instanceof LiteralNode) {
            if ('' === $node->value) {
                return CharSet::empty();
            }

            $set = CharSet::empty();
            foreach (\str_split($node->value) as $char) {
                $set = $set->union(CharSet::fromChar($char));
            }

            return $set;
        }

        if ($node instanceof CharLiteralNode) {
            return CharSet::fromCodePoint($node->codePoint);
        }

        if ($node instanceof ControlCharNode) {
            return CharSet::fromCodePoint($node->codePoint);
        }

        if ($node instanceof CharTypeNode) {
            return match ($node->value) {
                'd' => CharSet::fromRange(\ord('0'), \ord('9')),
                'D' => CharSet::fromRange(\ord('0'), \ord('9'))->complement(),
                'w' => $this->wordCharSet(),
                'W' => $this->wordCharSet()->complement(),
                's' => $this->spaceCharSet(),
                'S' => $this->spaceCharSet()->complement(),
                default => throw new ComplexityException('Unsupported character type in class: '.$node->value.'.', $node->getStartPosition(), $this->pattern),
            };
        }

        if ($node instanceof CharClassNode) {
            $set = $this->buildCharClassExpression($node->expression);

            return $node->isNegated ? $set->complement() : $set;
        }

        if ($node instanceof ClassOperationNode) {
            $left = $this->buildCharClassExpression($node->left);
            $right = $this->buildCharClassExpression($node->right);

            return match ($node->type) {
                ClassOperationType::INTERSECTION => $left->intersect($right),
                ClassOperationType::SUBTRACTION => $left->subtract($right),
            };
        }

        throw new ComplexityException('Unsupported character class expression.', $node->getStartPosition(), $this->pattern);
    }

    /**
     * @throws ComplexityException
     */
    private function buildStar(NodeInterface $node, SolverOptions $options): NfaFragment
    {
        $start = $this->builder->createState();
        $end = $this->builder->createState();
        $fragment = $this->buildNode($node, $options);

        $this->builder->addEpsilon($start, $end);
        $this->builder->addEpsilon($start, $fragment->startState);
        foreach ($fragment->acceptStates as $acceptState) {
            $this->builder->addEpsilon($acceptState, $fragment->startState);
            $this->builder->addEpsilon($acceptState, $end);
        }

        return new NfaFragment($start, [$end]);
    }

    /**
     * @throws ComplexityException
     */
    private function repeatNode(NodeInterface $node, SolverOptions $options, int $count): NfaFragment
    {
        if (0 === $count) {
            return $this->epsilonFragment();
        }

        $fragment = $this->buildNode($node, $options);
        for ($i = 1; $i < $count; $i++) {
            $fragment = $this->concatenate($fragment, $this->buildNode($node, $options));
        }

        return $fragment;
    }

    /**
     * @throws ComplexityException
     */
    private function buildBoundedRepeat(
        NodeInterface $node,
        SolverOptions $options,
        int $min,
        int $max,
    ): NfaFragment {
        if (0 === $max) {
            return $this->epsilonFragment();
        }

        if (0 === $min) {
            $start = $this->builder->createState();
            $acceptStates = [$start];
            $currentAccepts = [$start];

            for ($i = 1; $i <= $max; $i++) {
                $fragment = $this->buildNode($node, $options);
                foreach ($currentAccepts as $acceptState) {
                    $this->builder->addEpsilon($acceptState, $fragment->startState);
                }

                $currentAccepts = $fragment->acceptStates;
                $acceptStates = \array_merge($acceptStates, $currentAccepts);
            }

            return new NfaFragment($start, \array_values(\array_unique($acceptStates)));
        }

        $fragment = $this->buildNode($node, $options);
        $start = $fragment->startState;
        $acceptStates = [];
        $currentAccepts = $fragment->acceptStates;

        if (1 >= $min) {
            $acceptStates = \array_merge($acceptStates, $currentAccepts);
        }

        for ($i = 2; $i <= $max; $i++) {
            $next = $this->buildNode($node, $options);
            foreach ($currentAccepts as $acceptState) {
                $this->builder->addEpsilon($acceptState, $next->startState);
            }

            $currentAccepts = $next->acceptStates;
            if ($i >= $min) {
                $acceptStates = \array_merge($acceptStates, $currentAccepts);
            }
        }

        return new NfaFragment($start, \array_values(\array_unique($acceptStates)));
    }

    private function concatenate(NfaFragment $first, NfaFragment $second): NfaFragment
    {
        foreach ($first->acceptStates as $acceptState) {
            $this->builder->addEpsilon($acceptState, $second->startState);
        }

        return new NfaFragment($first->startState, $second->acceptStates);
    }

    private function epsilonFragment(): NfaFragment
    {
        $state = $this->builder->createState();

        return new NfaFragment($state, [$state]);
    }

    private function wordCharSet(): CharSet
    {
        $letters = CharSet::fromRange(\ord('A'), \ord('Z'))
            ->union(CharSet::fromRange(\ord('a'), \ord('z')));
        $digits = CharSet::fromRange(\ord('0'), \ord('9'));
        $underscore = CharSet::fromChar('_');

        return $letters->union($digits)->union($underscore);
    }

    private function spaceCharSet(): CharSet
    {
        $space = CharSet::fromChar(' ');
        $tab = CharSet::fromCodePoint(0x09);
        $newline = CharSet::fromCodePoint(0x0A);
        $carriage = CharSet::fromCodePoint(0x0D);
        $formFeed = CharSet::fromCodePoint(0x0C);
        $vertical = CharSet::fromCodePoint(0x0B);

        return $space->union($tab)->union($newline)->union($carriage)->union($formFeed)->union($vertical);
    }

    /**
     * @throws ComplexityException
     */
    private function extractCodePoint(NodeInterface $node): int
    {
        if ($node instanceof LiteralNode) {
            if ('' === $node->value) {
                return 0;
            }

            return \ord($node->value[0]);
        }

        if ($node instanceof CharLiteralNode) {
            return $node->codePoint;
        }

        if ($node instanceof ControlCharNode) {
            return $node->codePoint;
        }

        throw new ComplexityException('Unsupported range endpoint in character class.', $node->getStartPosition(), $this->pattern);
    }

    /**
     * @return array{0:int, 1:int|null}
     */
    private function parseQuantifierRange(string $quantifier): array
    {
        return match ($quantifier) {
            '*' => [0, null],
            '+' => [1, null],
            '?' => [0, 1],
            default => \preg_match('/^\{(\d++)(?:,(\d*+))?\}$/', $quantifier, $matches) ?
                (isset($matches[2]) ? ('' === $matches[2] ?
                    [(int) $matches[1], null] :
                    [(int) $matches[1], (int) $matches[2]]
                ) :
                    [(int) $matches[1], (int) $matches[1]]
                ) :
                [1, 1],
        };
    }

    private function applyCaseInsensitive(CharSet $charSet): CharSet
    {
        if (!$this->caseInsensitive) {
            return $charSet;
        }

        $expanded = $charSet;
        for ($i = \ord('A'); $i <= \ord('Z'); $i++) {
            $lower = $i + 32;
            if ($charSet->contains($i)) {
                $expanded = $expanded->union(CharSet::fromCodePoint($lower));
            }
            if ($charSet->contains($lower)) {
                $expanded = $expanded->union(CharSet::fromCodePoint($i));
            }
        }

        return $expanded;
    }
}
