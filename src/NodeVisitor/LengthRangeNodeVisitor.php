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

use RegexParser\Node;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CalloutNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\LiteralNode;
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
 * A visitor that calculates the minimum and maximum possible lengths of strings that match the AST.
 *
 * Purpose: This visitor traverses the Abstract Syntax Tree (AST) of a regular expression
 * and computes the range of string lengths that could potentially match the pattern.
 * This is invaluable for input validation, performance optimization, and understanding
 * the constraints of a regex pattern.
 *
 * @extends AbstractNodeVisitor<array{0: int, 1: int|null}>
 */
final class LengthRangeNodeVisitor extends AbstractNodeVisitor
{
    /**
     * Visits a RegexNode and returns the length range of its pattern.
     *
     * @param Node\RegexNode $node the `RegexNode` representing the entire regular expression
     *
     * @return array{0: int, 1: int|null} the minimum and maximum lengths (null for infinite)
     */
    #[\Override]
    public function visitRegex(RegexNode $node): array
    {
        return $node->pattern->accept($this);
    }

    /**
     * Visits an AlternationNode and returns the combined length range of its alternatives.
     *
     * @param Node\AlternationNode $node the `AlternationNode` representing a choice between patterns
     *
     * @return array{0: int, 1: int|null} the minimum and maximum lengths across all alternatives
     */
    #[\Override]
    public function visitAlternation(AlternationNode $node): array
    {
        $min = \PHP_INT_MAX;
        $max = 0;
        $hasInfinite = false;

        foreach ($node->alternatives as $alt) {
            [$altMin, $altMax] = $alt->accept($this);
            $min = min($min, $altMin);
            if (null === $altMax) {
                $hasInfinite = true;
            } else {
                $max = max($max, $altMax);
            }
        }

        return [$min, $hasInfinite ? null : $max];
    }

    /**
     * Visits a SequenceNode and returns the sum of length ranges of its children.
     *
     * @param Node\SequenceNode $node the `SequenceNode` representing a series of regex components
     *
     * @return array{0: int, 1: int|null} the total minimum and maximum lengths
     */
    #[\Override]
    public function visitSequence(SequenceNode $node): array
    {
        $totalMin = 0;
        $totalMax = 0;
        $hasInfinite = false;

        foreach ($node->children as $child) {
            [$childMin, $childMax] = $child->accept($this);
            $totalMin += $childMin;
            if (null === $childMax) {
                $hasInfinite = true;
            } else {
                $totalMax += $childMax;
            }
        }

        return [$totalMin, $hasInfinite ? null : $totalMax];
    }

    /**
     * Visits a GroupNode and returns the length range of its child.
     *
     * @param Node\GroupNode $node the `GroupNode` representing a grouping construct
     *
     * @return array{0: int, 1: int|null} the length range of the grouped content
     */
    #[\Override]
    public function visitGroup(GroupNode $node): array
    {
        // Lookarounds are zero-width assertions
        if (\in_array($node->type, [
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE,
        ], true)) {
            return [0, 0];
        }

        return $node->child->accept($this);
    }

    /**
     * Visits a QuantifierNode and calculates the length range based on repetition.
     *
     * @param Node\QuantifierNode $node the `QuantifierNode` representing a repetition operator
     *
     * @return array{0: int, 1: int|null} the minimum and maximum lengths for the quantified element
     */
    #[\Override]
    public function visitQuantifier(QuantifierNode $node): array
    {
        [$childMin, $childMax] = $node->node->accept($this);

        // Parse quantifier
        $range = $this->parseQuantifierRange($node->quantifier);
        $qMin = $range[0];
        $qMax = $range[1];

        $min = $childMin * $qMin;
        $max = null;
        if (null !== $childMax && null !== $qMax) {
            $max = $childMax * $qMax;
        }

        return [$min, $max];
    }

    /**
     * Visits a LiteralNode and returns [1, 1].
     *
     * @param Node\LiteralNode $node the `LiteralNode` representing a literal character
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitLiteral(LiteralNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits a CharTypeNode and returns [1, 1].
     *
     * @param Node\CharTypeNode $node the `CharTypeNode` representing a character type
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitCharType(CharTypeNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits a DotNode and returns [1, 1].
     *
     * @param Node\DotNode $node the `DotNode` representing the wildcard dot
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitDot(DotNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits an AnchorNode and returns [0, 0].
     *
     * @param Node\AnchorNode $node the `AnchorNode` representing a positional anchor
     *
     * @return array{0: int, 1: int|null} always [0, 0]
     */
    #[\Override]
    public function visitAnchor(AnchorNode $node): array
    {
        return [0, 0];
    }

    /**
     * Visits an AssertionNode and returns [0, 0].
     *
     * @param Node\AssertionNode $node the `AssertionNode` representing a zero-width assertion
     *
     * @return array{0: int, 1: int|null} always [0, 0]
     */
    #[\Override]
    public function visitAssertion(AssertionNode $node): array
    {
        return [0, 0];
    }

    /**
     * Visits a CharClassNode and returns [1, 1].
     *
     * @param Node\CharClassNode $node the `CharClassNode` representing a character class
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitCharClass(CharClassNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits a RangeNode and returns [1, 1].
     *
     * @param Node\RangeNode $node the `RangeNode` representing a character range
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitRange(RangeNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits a BackrefNode and returns [0, null] (can be any length).
     *
     * @param Node\BackrefNode $node the `BackrefNode` representing a backreference
     *
     * @return array{0: int, 1: int|null} [0, null]
     */
    #[\Override]
    public function visitBackref(BackrefNode $node): array
    {
        return [0, null]; // Backrefs can match variable lengths
    }

    /**
     * Visits a UnicodeNode and returns [1, 1].
     *
     * @param Node\UnicodeNode $node the `UnicodeNode` representing a Unicode escape
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitUnicode(UnicodeNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits a UnicodePropNode and returns [1, 1].
     *
     * @param Node\UnicodePropNode $node the `UnicodePropNode` representing a Unicode property
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitUnicodeProp(UnicodePropNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits a PosixClassNode and returns [1, 1].
     *
     * @param Node\PosixClassNode $node the `PosixClassNode` representing a POSIX character class
     *
     * @return array{0: int, 1: int|null} always [1, 1]
     */
    #[\Override]
    public function visitPosixClass(PosixClassNode $node): array
    {
        return [1, 1];
    }

    /**
     * Visits a CommentNode and returns [0, 0].
     *
     * @param Node\CommentNode $node the `CommentNode` representing an inline comment
     *
     * @return array{0: int, 1: int|null} always [0, 0]
     */
    #[\Override]
    public function visitComment(CommentNode $node): array
    {
        return [0, 0];
    }

    /**
     * Visits a ConditionalNode and returns the length range of the chosen branch.
     *
     * @param Node\ConditionalNode $node the `ConditionalNode` representing a conditional sub-pattern
     *
     * @return array{0: int, 1: int|null} the length range of the conditional
     */
    #[\Override]
    public function visitConditional(ConditionalNode $node): array
    {
        // For simplicity, take the max of yes and no
        [$yesMin, $yesMax] = $node->yes->accept($this);
        [$noMin, $noMax] = $node->no->accept($this);

        $min = min($yesMin, $noMin);
        $max = null;
        if (null !== $yesMax && null !== $noMax) {
            $max = max($yesMax, $noMax);
        }

        return [$min, $max];
    }

    /**
     * Visits a SubroutineNode and returns [0, null] (complex recursion).
     *
     * @param Node\SubroutineNode $node the `SubroutineNode` representing a subroutine call
     *
     * @return array{0: int, 1: int|null} [0, null]
     */
    #[\Override]
    public function visitSubroutine(SubroutineNode $node): array
    {
        return [0, null]; // Subroutines can be complex
    }

    /**
     * Visits a PcreVerbNode and returns [0, 0].
     *
     * @param Node\PcreVerbNode $node the `PcreVerbNode` representing a PCRE verb
     *
     * @return array{0: int, 1: int|null} always [0, 0]
     */
    #[\Override]
    public function visitPcreVerb(PcreVerbNode $node): array
    {
        return [0, 0];
    }

    /**
     * Visits a DefineNode and returns [0, 0].
     *
     * @param Node\DefineNode $node the `DefineNode` representing a define block
     *
     * @return array{0: int, 1: int|null} always [0, 0]
     */
    #[\Override]
    public function visitDefine(DefineNode $node): array
    {
        return [0, 0];
    }

    #[\Override]
    public function visitLimitMatch(LimitMatchNode $node): array
    {
        return [0, 0];
    }

    #[\Override]
    public function visitCallout(CalloutNode $node): array
    {
        return [0, 0];
    }

    #[\Override]
    public function visitKeep(KeepNode $node): array
    {
        return [0, 0];
    }

    /**
     * Parses a quantifier string into min and max.
     *
     * @param string $q the quantifier
     *
     * @return array{0: int, 1: int|null} min and max
     */
    private function parseQuantifierRange(string $q): array
    {
        return match ($q) {
            '*' => [0, null],
            '+' => [1, null],
            '?' => [0, 1],
            default => preg_match('/^\{(\d++)(?:,(\d*+))?\}$/', $q, $m) ?
                (isset($m[2]) ? ('' === $m[2] ?
                    [(int) $m[1], null] :
                    [(int) $m[1], (int) $m[2]]
                ) :
                    [(int) $m[1], (int) $m[1]]
                ) :
                [1, 1], // fallback
        };
    }
}
