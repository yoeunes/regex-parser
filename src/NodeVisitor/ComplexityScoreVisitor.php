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

namespace RegexParser\NodeVisitor;

use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * A visitor that calculates a numeric "complexity score" for the regex.
 * This score can be used to heuristically identify overly complex patterns
 * that may be inefficient or difficult to maintain.
 *
 * @implements NodeVisitorInterface<int>
 */
class ComplexityScoreVisitor implements NodeVisitorInterface
{
    /**
     * Base score for a node.
     */
    private const int BASE_SCORE = 1;
    /**
     * Score multiplier for unbounded quantifiers (*, +, {n,}).
     */
    private const int UNBOUNDED_QUANTIFIER_SCORE = 10;
    /**
     * Score for complex constructs like lookarounds or backreferences.
     */
    private const int COMPLEX_CONSTRUCT_SCORE = 5;
    /**
     * Exponential multiplier for nested quantifiers.
     */
    private const int NESTING_MULTIPLIER = 2;

    /**
     * Tracks the depth of nested quantifiers.
     */
    private int $quantifierDepth = 0;

    public function visitRegex(RegexNode $node): int
    {
        // Reset state for this run
        $this->quantifierDepth = 0;

        // The score of a regex is the score of its pattern
        return $node->pattern->accept($this);
    }

    public function visitAlternation(AlternationNode $node): int
    {
        // Score is the sum of all alternatives, plus a base score for the alternation itself
        $score = self::BASE_SCORE;
        foreach ($node->alternatives as $alt) {
            $score += $alt->accept($this);
        }

        return $score;
    }

    public function visitSequence(SequenceNode $node): int
    {
        // Score is the sum of all children
        $score = 0;
        foreach ($node->children as $child) {
            $score += $child->accept($this);
        }

        return $score;
    }

    public function visitGroup(GroupNode $node): int
    {
        $childScore = $node->child->accept($this);

        // Lookarounds are considered complex
        if (\in_array(
            $node->type,
            [
                GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
                GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
                GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
                GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
            ],
            true,
        )) {
            return self::COMPLEX_CONSTRUCT_SCORE + $childScore;
        }

        return self::BASE_SCORE + $childScore;
    }

    public function visitQuantifier(QuantifierNode $node): int
    {
        $quant = $node->quantifier;
        $isUnbounded = \in_array($quant, ['*', '+'], true) || preg_match('/^\{\d+,\}$/', $quant);
        $score = 0;

        if ($isUnbounded) {
            $score += self::UNBOUNDED_QUANTIFIER_SCORE;
            if ($this->quantifierDepth > 0) {
                // Exponentially penalize nested unbounded quantifiers
                $score *= (self::NESTING_MULTIPLIER * $this->quantifierDepth);
            }
            $this->quantifierDepth++;
        } else {
            // Bounded quantifiers are simpler
            $score += self::BASE_SCORE;
        }

        // Add the score of the quantified node
        $score += $node->node->accept($this);

        if ($isUnbounded) {
            $this->quantifierDepth--;
        }

        return $score;
    }

    public function visitCharClass(CharClassNode $node): int
    {
        // Score is the sum of parts inside the class
        $score = self::BASE_SCORE;
        foreach ($node->parts as $part) {
            $score += $part->accept($this);
        }

        return $score;
    }

    public function visitBackref(BackrefNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    public function visitConditional(ConditionalNode $node): int
    {
        // Conditionals are highly complex
        $score = self::COMPLEX_CONSTRUCT_SCORE * 2;
        $score += $node->condition->accept($this);
        $score += $node->yes->accept($this);
        $score += $node->no->accept($this);

        return $score;
    }

    public function visitSubroutine(SubroutineNode $node): int
    {
        // Subroutines/recursion are highly complex
        return self::COMPLEX_CONSTRUCT_SCORE * 2;
    }

    // --- Simple nodes have a base score of 1 ---

    public function visitLiteral(LiteralNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitCharType(CharTypeNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitDot(DotNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitAnchor(AnchorNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitAssertion(AssertionNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitKeep(KeepNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitRange(RangeNode $node): int
    {
        return self::BASE_SCORE + $node->start->accept($this) + $node->end->accept($this);
    }

    public function visitUnicode(UnicodeNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitUnicodeProp(UnicodePropNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitOctal(OctalNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitOctalLegacy(OctalLegacyNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitPosixClass(PosixClassNode $node): int
    {
        return self::BASE_SCORE;
    }

    public function visitComment(CommentNode $node): int
    {
        // Comments do not add to complexity
        return 0;
    }

    public function visitPcreVerb(PcreVerbNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }
}
