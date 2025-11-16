<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\NodeVisitor;

use RegexParser\Node\AbstractNode;
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
use RegexParser\Node\NodeInterface;
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
 * A visitor that transforms the AST to apply optimizations.
 * It returns a new (or identical) NodeInterface for each node visited.
 * This allows for true AST-to-AST transformation.
 *
 * @implements NodeVisitorInterface<NodeInterface>
 */
class OptimizerNodeVisitor implements NodeVisitorInterface
{
    private string $flags = '';

    public function visitRegex(RegexNode $node): NodeInterface
    {
        $this->flags = $node->flags;
        $optimizedPattern = $node->pattern->accept($this);

        if ($optimizedPattern === $node->pattern) {
            return $node; // No changes
        }

        return new RegexNode($optimizedPattern, $node->flags, $node->delimiter, $node->startPos, $node->endPos);
    }

    public function visitAlternation(AlternationNode $node): NodeInterface
    {
        $optimizedAlts = [];
        $hasChanged = false;
        foreach ($node->alternatives as $alt) {
            $optimizedAlt = $alt->accept($this);
            $optimizedAlts[] = $optimizedAlt;
            if ($optimizedAlt !== $alt) {
                $hasChanged = true;
            }
        }

        if (!$hasChanged) {
            return $node;
        }

        return new AlternationNode($optimizedAlts, $node->startPos, $node->endPos);
    }

    public function visitSequence(SequenceNode $node): NodeInterface
    {
        $optimizedChildren = [];
        $hasChanged = false;
        foreach ($node->children as $child) {
            $optimizedChild = $child->accept($this);
            $optimizedChildren[] = $optimizedChild;
            if ($optimizedChild !== $child) {
                $hasChanged = true;
            }
        }

        // Example: Apply (a)(a*) -> a+
        // This is complex and requires analyzing the $optimizedChildren array
        // for specific patterns. For v1, we focus on node-level optimizations.

        if (!$hasChanged) {
            return $node;
        }

        return new SequenceNode($optimizedChildren, $node->startPos, $node->endPos);
    }

    public function visitGroup(GroupNode $node): NodeInterface
    {
        $optimizedChild = $node->child->accept($this);

        // Optimization: (?:a) -> a
        // A non-capturing group with a single, simple child can be unwrapped.
        if (
            GroupType::T_GROUP_NON_CAPTURING === $node->type
            && ($optimizedChild instanceof LiteralNode
                || $optimizedChild instanceof CharTypeNode
                || $optimizedChild instanceof DotNode)
        ) {
            return $optimizedChild; // Return the child directly
        }

        // Optimization: (?:[abc]) -> [abc]
        // A non-capturing group with only a CharClassNode can be unwrapped.
        if (
            GroupType::T_GROUP_NON_CAPTURING === $node->type
            && $optimizedChild instanceof CharClassNode
        ) {
            return $optimizedChild;
        }

        // Optimization: (?:a|b|c) -> [abc]
        // A non-capturing group with an alternation of single literals.
        if (
            GroupType::T_GROUP_NON_CAPTURING === $node->type
            && $optimizedChild instanceof AlternationNode
            && $this->canAlternationBeCharClass($optimizedChild)
        ) {
            $parts = [];
            foreach ($optimizedChild->alternatives as $alt) {
                /** @var LiteralNode $alt */
                $parts[] = $alt;
            }

            // Return a new CharClassNode
            return new CharClassNode($parts, false, $node->startPos, $node->endPos);
        }

        // Re-build group if child changed
        if ($optimizedChild !== $node->child) {
            return new GroupNode($optimizedChild, $node->type, $node->name, $node->flags, $node->startPos, $node->endPos);
        }

        return $node; // No changes
    }

    /**
     * Checks if an AlternationNode contains only single, non-meta literal characters.
     */
    private function canAlternationBeCharClass(AlternationNode $node): bool
    {
        if (empty($node->alternatives)) {
            return false;
        }

        $meta = [']' => true, '\\' => true, '^' => true, '-' => true];

        foreach ($node->alternatives as $alt) {
            if (!$alt instanceof LiteralNode) {
                return false;
            }
            if (\mb_strlen($alt->value) > 1) {
                return false; // Not a single char
            }
            if (isset($meta[$alt->value])) {
                return false; // Meta char, safer to leave as alternation
            }
        }

        return true;
    }

    public function visitQuantifier(QuantifierNode $node): NodeInterface
    {
        $optimizedNode = $node->node->accept($this);

        if ($optimizedNode !== $node->node) {
            return new QuantifierNode($optimizedNode, $node->quantifier, $node->type, $node->startPos, $node->endPos);
        }

        return $node;
    }

    public function visitCharClass(CharClassNode $node): NodeInterface
    {
        // Optimization: [0-9] -> \d (if /u is not set)
        if (!$node->isNegated && 1 === \count($node->parts) && !str_contains($this->flags, 'u')) {
            $part = $node->parts[0];
            if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                if ('0' === $part->start->value && '9' === $part->end->value) {
                    return new CharTypeNode('d', $node->startPos, $node->endPos);
                }
            }
        }

        // Optimization: [a-zA-Z0-9_] -> \w (if /u is not set)
        if (!$node->isNegated && 4 === \count($node->parts) && !str_contains($this->flags, 'u')) {
            if ($this->isFullWordClass($node)) {
                return new CharTypeNode('w', $node->startPos, $node->endPos);
            }
        }

        // Recurse into parts (e.g., if a range could be optimized)
        $optimizedParts = [];
        $hasChanged = false;
        foreach ($node->parts as $part) {
            $optimizedPart = $part->accept($this);
            $optimizedParts[] = $optimizedPart;
            if ($optimizedPart !== $part) {
                $hasChanged = true;
            }
        }

        if ($hasChanged) {
            return new CharClassNode($optimizedParts, $node->isNegated, $node->startPos, $node->endPos);
        }

        return $node;
    }

    private function isFullWordClass(CharClassNode $node): bool
    {
        $partsFound = ['a-z' => false, 'A-Z' => false, '0-9' => false, '_' => false];
        foreach ($node->parts as $part) {
            if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                $range = $part->start->value.'-'.$part->end->value;
                if (isset($partsFound[$range])) {
                    $partsFound[$range] = true;
                }
            } elseif ($part instanceof LiteralNode && '_' === $part->value) {
                $partsFound['_'] = true;
            }
        }

        return !\in_array(false, $partsFound, true);
    }

    public function visitRange(RangeNode $node): NodeInterface
    {
        $optimizedStart = $node->start->accept($this);
        $optimizedEnd = $node->end->accept($this);

        if ($optimizedStart !== $node->start || $optimizedEnd !== $node->end) {
            // Ensure we still have valid types for a Range
            if ($optimizedStart instanceof LiteralNode && $optimizedEnd instanceof LiteralNode) {
                return new RangeNode($optimizedStart, $optimizedEnd, $node->startPos, $node->endPos);
            }
            // If optimization made the range invalid (e.g., [a-\w]),
            // we should probably revert to the original node.
            // But for now, we assume optimizations don't break range semantics.
        }

        return $node;
    }

    public function visitConditional(ConditionalNode $node): NodeInterface
    {
        $optimizedCond = $node->condition->accept($this);
        $optimizedYes = $node->yes->accept($this);
        $optimizedNo = $node->no->accept($this);

        if ($optimizedCond !== $node->condition || $optimizedYes !== $node->yes || $optimizedNo !== $node->no) {
            return new ConditionalNode($optimizedCond, $optimizedYes, $optimizedNo, $node->startPos, $node->endPos);
        }

        return $node;
    }

    // --- Simple nodes are leaves; they cannot be optimized further ---

    public function visitLiteral(LiteralNode $node): NodeInterface
    {
        return $node;
    }

    public function visitCharType(CharTypeNode $node): NodeInterface
    {
        return $node;
    }

    public function visitDot(DotNode $node): NodeInterface
    {
        return $node;
    }

    public function visitAnchor(AnchorNode $node): NodeInterface
    {
        return $node;
    }

    public function visitAssertion(AssertionNode $node): NodeInterface
    {
        return $node;
    }

    public function visitKeep(KeepNode $node): NodeInterface
    {
        return $node;
    }

    public function visitBackref(BackrefNode $node): NodeInterface
    {
        return $node;
    }

    public function visitUnicode(UnicodeNode $node): NodeInterface
    {
        return $node;
    }

    public function visitUnicodeProp(UnicodePropNode $node): NodeInterface
    {
        return $node;
    }

    public function visitOctal(OctalNode $node): NodeInterface
    {
        return $node;
    }

    public function visitOctalLegacy(OctalLegacyNode $node): NodeInterface
    {
        return $node;
    }

    public function visitPosixClass(PosixClassNode $node): NodeInterface
    {
        return $node;
    }

    public function visitComment(CommentNode $node): NodeInterface
    {
        return $node;
    }

    public function visitSubroutine(SubroutineNode $node): NodeInterface
    {
        return $node;
    }

    public function visitPcreVerb(PcreVerbNode $node): NodeInterface
    {
        return $node;
    }
}
