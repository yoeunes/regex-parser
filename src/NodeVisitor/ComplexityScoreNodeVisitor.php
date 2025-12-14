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
use RegexParser\Node\GroupType;

/**
 * High-performance visitor that calculates numeric complexity scores for regex patterns.
 *
 * This optimized visitor provides intelligent complexity analysis with caching and
 * streamlined scoring algorithms for maximum performance while detecting ReDoS patterns.
 *
 * @extends AbstractNodeVisitor<int>
 */
final class ComplexityScoreNodeVisitor extends AbstractNodeVisitor
{
    // Optimized scoring constants
    private const BASE_SCORE = 1;
    private const UNBOUNDED_QUANTIFIER_SCORE = 10;
    private const COMPLEX_CONSTRUCT_SCORE = 5;
    private const NESTING_MULTIPLIER = 2;

    // Intelligent caching for expensive operations
    /**
     * @var array<string, bool>
     */
    private static array $unboundedQuantifierCache = [];

    // Minimal state tracking
    private int $quantifierDepth = 0;

    #[\Override]
    public function visitRegex(Node\RegexNode $node): int
    {
        // Reset state for this run
        $this->quantifierDepth = 0;

        // The score of a regex is the score of its pattern
        return $node->pattern->accept($this);
    }

    #[\Override]
    public function visitAlternation(Node\AlternationNode $node): int
    {
        // Optimized: sum all alternatives with base score
        $score = self::BASE_SCORE;
        foreach ($node->alternatives as $alt) {
            $score += $alt->accept($this);
        }

        return $score;
    }

    #[\Override]
    public function visitSequence(Node\SequenceNode $node): int
    {
        // Optimized: direct sum of all children
        $score = 0;
        foreach ($node->children as $child) {
            $score += $child->accept($this);
        }

        return $score;
    }

    #[\Override]
    public function visitGroup(Node\GroupNode $node): int
    {
        $childScore = $node->child->accept($this);

        // Lookarounds are considered complex - optimized enum check
        return match ($node->type) {
            GroupType::T_GROUP_LOOKAHEAD_POSITIVE,
            GroupType::T_GROUP_LOOKAHEAD_NEGATIVE,
            GroupType::T_GROUP_LOOKBEHIND_POSITIVE,
            GroupType::T_GROUP_LOOKBEHIND_NEGATIVE => self::COMPLEX_CONSTRUCT_SCORE + $childScore,
            default => self::BASE_SCORE + $childScore,
        };
    }

    #[\Override]
    public function visitQuantifier(Node\QuantifierNode $node): int
    {
        $quant = $node->quantifier;
        $isUnbounded = $this->isUnboundedQuantifier($quant);
        $score = 0;

        if ($isUnbounded) {
            $score += self::UNBOUNDED_QUANTIFIER_SCORE;
            if ($this->quantifierDepth > 0) {
                // Exponentially penalize nested unbounded quantifiers (ReDoS detection)
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

    #[\Override]
    public function visitCharClass(Node\CharClassNode $node): int
    {
        // Optimized: sum parts inside character class
        $score = self::BASE_SCORE;
        $expression = $node->expression;

        if ($expression instanceof Node\AlternationNode) {
            foreach ($expression->alternatives as $part) {
                $score += $part->accept($this);
            }
        } else {
            $score += $expression->accept($this);
        }

        return $score;
    }

    #[\Override]
    public function visitBackref(Node\BackrefNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    #[\Override]
    public function visitConditional(Node\ConditionalNode $node): int
    {
        // Conditionals are highly complex - optimized calculation
        return self::COMPLEX_CONSTRUCT_SCORE * 2
            + $node->condition->accept($this)
            + $node->yes->accept($this)
            + $node->no->accept($this);
    }

    #[\Override]
    public function visitSubroutine(Node\SubroutineNode $node): int
    {
        // Subroutines/recursion are highly complex
        return self::COMPLEX_CONSTRUCT_SCORE * 2;
    }

    #[\Override]
    public function visitLiteral(Node\LiteralNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitCharType(Node\CharTypeNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitDot(Node\DotNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitAnchor(Node\AnchorNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitAssertion(Node\AssertionNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitKeep(Node\KeepNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitRange(Node\RangeNode $node): int
    {
        // Optimized: range score includes start and end nodes
        return self::BASE_SCORE + $node->start->accept($this) + $node->end->accept($this);
    }

    #[\Override]
    public function visitUnicode(Node\UnicodeNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitUnicodeProp(Node\UnicodePropNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitOctal(Node\OctalNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitOctalLegacy(Node\OctalLegacyNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitPosixClass(Node\PosixClassNode $node): int
    {
        return self::BASE_SCORE;
    }

    #[\Override]
    public function visitComment(Node\CommentNode $node): int
    {
        // Comments do not add to complexity
        return 0;
    }

    #[\Override]
    public function visitPcreVerb(Node\PcreVerbNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    #[\Override]
    public function visitDefine(Node\DefineNode $node): int
    {
        // DEFINE blocks add complexity from their content
        return self::COMPLEX_CONSTRUCT_SCORE + $node->content->accept($this);
    }

    #[\Override]
    public function visitLimitMatch(Node\LimitMatchNode $node): int
    {
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    #[\Override]
    public function visitCallout(Node\CalloutNode $node): int
    {
        // Callouts introduce external logic and break regex flow, making them complex.
        return self::COMPLEX_CONSTRUCT_SCORE;
    }

    /**
     * High-performance cached unbounded quantifier detection.
     */
    private function isUnboundedQuantifier(string $quant): bool
    {
        // Return cached result if available
        if (isset(self::$unboundedQuantifierCache[$quant])) {
            return self::$unboundedQuantifierCache[$quant];
        }

        // Fast array lookup for common cases
        if (\in_array($quant, ['*', '+'], true)) {
            return self::$unboundedQuantifierCache[$quant] = true;
        }

        // Regex check for {n,} pattern
        $isUnbounded = 1 === preg_match('/^\{\d++,\}$/', $quant);

        return self::$unboundedQuantifierCache[$quant] = $isUnbounded;
    }
}
