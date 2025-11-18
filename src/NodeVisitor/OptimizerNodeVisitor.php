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
 * Optimizations include:
 * - Merging adjacent LiteralNodes
 * - Flattening nested SequenceNodes and AlternationNodes
 * - Converting simple alternations to CharacterClasses (e.g., a|b|c -> [abc])
 * - Simplifying character classes (e.g., [0-9] -> \d)
 * - Removing redundant non-capturing groups
 *
 * @implements NodeVisitorInterface<NodeInterface>
 */
class OptimizerNodeVisitor implements NodeVisitorInterface
{
    private string $flags = '';

    /**
     * Characters that are meta-characters inside a character class.
     *
     * @var array<string, true>
     */
    private const CHAR_CLASS_META = [']' => true, '\\' => true, '^' => true, '-' => true];

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

            // Optimization: Flatten nested alternations (e.g., a|(b|c))
            if ($optimizedAlt instanceof AlternationNode) {
                array_push($optimizedAlts, ...$optimizedAlt->alternatives);
                $hasChanged = true;
            } else {
                $optimizedAlts[] = $optimizedAlt;
            }

            if ($optimizedAlt !== $alt) {
                $hasChanged = true;
            }
        }

        // Optimization: a|b|c -> [abc]
        if ($this->canAlternationBeCharClass($optimizedAlts)) {
            /* @var list<LiteralNode> $optimizedAlts */
            return new CharClassNode($optimizedAlts, false, $node->startPos, $node->endPos);
        }

        if (!$hasChanged) {
            return $node;
        }

        return new AlternationNode($optimizedAlts, $node->startPos, $node->endPos);
    }

    /**
     * Checks if an AlternationNode contains only single, non-meta literal characters.
     *
     * @param array<NodeInterface> $alternatives
     */
    private function canAlternationBeCharClass(array $alternatives): bool
    {
        if (empty($alternatives)) {
            return false;
        }

        foreach ($alternatives as $alt) {
            if (!$alt instanceof LiteralNode) {
                return false;
            }
            if (mb_strlen($alt->value) > 1) {
                return false; // Not a single char
            }
            if (isset(self::CHAR_CLASS_META[$alt->value])) {
                return false; // Meta char, safer to leave as alternation
            }
        }

        return true;
    }

    public function visitSequence(SequenceNode $node): NodeInterface
    {
        $optimizedChildren = [];
        $hasChanged = false;

        foreach ($node->children as $child) {
            $optimizedChild = $child->accept($this);

            // Optimization: Merge adjacent literals (e.g., "a" . "b" -> "ab")
            if ($optimizedChild instanceof LiteralNode && \count($optimizedChildren) > 0) {
                $prevNode = $optimizedChildren[\count($optimizedChildren) - 1];
                if ($prevNode instanceof LiteralNode) {
                    // Merge with previous node
                    $optimizedChildren[\count($optimizedChildren) - 1] = new LiteralNode(
                        $prevNode->value.$optimizedChild->value,
                        $prevNode->startPos,
                        $optimizedChild->endPos
                    );
                    $hasChanged = true;
                    continue;
                }
            }

            // Optimization: Flatten nested sequences (e.g., a(bc)d)
            if ($optimizedChild instanceof SequenceNode) {
                // This is safe because adjacent literals *within* the child
                // sequence have already been merged by its own visitSequence call.
                array_push($optimizedChildren, ...$optimizedChild->children);
                $hasChanged = true;
                continue;
            }

            // Optimization: Remove empty literals (e.g. from an empty group)
            if ($optimizedChild instanceof LiteralNode && '' === $optimizedChild->value) {
                $hasChanged = true;
                continue;
            }

            if ($optimizedChild !== $child) {
                $hasChanged = true;
            }

            $optimizedChildren[] = $optimizedChild;
        }

        if (!$hasChanged) {
            return $node;
        }

        // A sequence with one child is just that child
        if (1 === \count($optimizedChildren)) {
            return $optimizedChildren[0];
        }

        // A sequence with no children is an empty literal
        if (0 === \count($optimizedChildren)) {
            return new LiteralNode('', $node->startPos, $node->endPos);
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

        // Re-build group if child changed
        if ($optimizedChild !== $node->child) {
            return new GroupNode($optimizedChild, $node->type, $node->name, $node->flags, $node->startPos, $node->endPos);
        }

        return $node; // No changes
    }

    public function visitQuantifier(QuantifierNode $node): NodeInterface
    {
        $optimizedNode = $node->node->accept($this);

        // Optimization: (?:a)* -> a*
        // If the quantified node was a non-capturing group that got
        // optimized away (e.g. (?:a)), $optimizedNode is now just Literal(a).
        // The QuantifierNode constructor doesn't need to change, but
        // the CompilerNodeVisitor must be smart about adding (?:...) back
        // *only* if the quantified node is a Sequence or Alternation.
        // Your CompilerNodeVisitor already does this, so we are good.

        // TODO: Add (a*)* -> (a*)
        // TODO: Add a?a? -> a{0,2}

        if ($optimizedNode !== $node->node) {
            return new QuantifierNode($optimizedNode, $node->quantifier, $node->type, $node->startPos, $node->endPos);
        }

        return $node;
    }

    public function visitCharClass(CharClassNode $node): NodeInterface
    {
        // This is where the logic from your Rector visitor belongs.
        $isUnicode = str_contains($this->flags, 'u');

        // Optimization: [0-9] -> \d (only if NOT unicode)
        if (!$isUnicode && !$node->isNegated && 1 === \count($node->parts)) {
            $part = $node->parts[0];
            if ($part instanceof RangeNode && $part->start instanceof LiteralNode && $part->end instanceof LiteralNode) {
                if ('0' === $part->start->value && '9' === $part->end->value) {
                    return new CharTypeNode('d', $node->startPos, $node->endPos);
                }
            }
        }

        // Optimization: [a-zA-Z0-9_] -> \w (only if NOT unicode)
        if (!$isUnicode && !$node->isNegated && 4 === \count($node->parts)) {
            if ($this->isFullWordClass($node)) {
                return new CharTypeNode('w', $node->startPos, $node->endPos);
            }
        }

        // TODO: Optimization: [a-cdefg] -> [a-g] (merge ranges)
        // TODO: Optimization: [aa] -> [a] (deduplicate literals)

        // Recurse into parts (though not much to optimize in class parts)
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
        // Start and End are simple nodes, no need to visit
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
