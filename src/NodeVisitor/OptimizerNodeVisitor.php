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
 * Transforms the AST to apply optimizations, returning a new, simplified AST.
 *
 * Purpose: This visitor is the engine behind `Regex::optimize()`. It traverses the
 * AST and applies a series of rules to simplify the regex without changing its
 * meaning. This can lead to more readable and sometimes more performant patterns.
 * For contributors, this class is a great example of AST-to-AST transformation.
 * Each `visit` method can return a new, modified node, effectively rewriting
 * parts of the tree.
 *
 * @implements NodeVisitorInterface<Node\NodeInterface>
 */
class OptimizerNodeVisitor implements NodeVisitorInterface
{
    private const array CHAR_CLASS_META = [']' => true, '\\' => true, '^' => true, '-' => true];

    private string $flags = '';

    /**
     * Optimizes the root `RegexNode`.
     *
     * Purpose: This is the entry point for the optimization. It stores the regex
     * flags for context-aware optimizations (like unicode-dependent rules) and then
     * recursively optimizes the main pattern.
     *
     * @param Node\RegexNode $node the root node of the AST
     *
     * @return Node\NodeInterface the new, optimized root node
     */
    public function visitRegex(Node\RegexNode $node): Node\NodeInterface
    {
        $this->flags = $node->flags;
        $optimizedPattern = $node->pattern->accept($this);

        if ($optimizedPattern === $node->pattern) {
            return $node;
        }

        return new Node\RegexNode($optimizedPattern, $node->flags, $node->delimiter, $node->startPosition, $node->endPosition);
    }

    /**
     * Optimizes an `AlternationNode`.
     *
     * Purpose: This method applies two key optimizations:
     * 1.  **Flattening:** It merges nested alternations (e.g., `a|(b|c)` becomes `a|b|c`).
     * 2.  **Character Class Conversion:** It converts simple alternations of single
     *     characters into a more efficient character class (e.g., `a|b|c` becomes `[abc]`).
     *
     * @param Node\AlternationNode $node the alternation node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which could be an `AlternationNode` or `CharClassNode`)
     */
    public function visitAlternation(Node\AlternationNode $node): Node\NodeInterface
    {
        $optimizedAlts = [];
        $hasChanged = false;

        foreach ($node->alternatives as $alt) {
            $optimizedAlt = $alt->accept($this);

            if ($optimizedAlt instanceof Node\AlternationNode) {
                array_push($optimizedAlts, ...$optimizedAlt->alternatives);
                $hasChanged = true;
            } else {
                $optimizedAlts[] = $optimizedAlt;
            }

            if ($optimizedAlt !== $alt) {
                $hasChanged = true;
            }
        }

        if ($this->canAlternationBeCharClass($optimizedAlts)) {
            /* @var list<Node\LiteralNode> $optimizedAlts */
            return new Node\CharClassNode($optimizedAlts, false, $node->startPosition, $node->endPosition);
        }

        if (!$hasChanged) {
            return $node;
        }

        return new Node\AlternationNode($optimizedAlts, $node->startPosition, $node->endPosition);
    }

    /**
     * Optimizes a `SequenceNode`.
     *
     * Purpose: This method applies several optimizations to sequences:
     * 1.  **Literal Merging:** It combines adjacent `LiteralNode`s into a single node
     *     (e.g., the sequence `('a', 'b')` becomes `('ab')`).
     * 2.  **Flattening:** It merges nested sequences into the parent sequence.
     * 3.  **Empty Node Removal:** It removes empty `LiteralNode`s that might result
     *     from other optimizations (like an empty group).
     *
     * @param Node\SequenceNode $node the sequence node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which could be a `SequenceNode` or a single child node)
     */
    public function visitSequence(Node\SequenceNode $node): Node\NodeInterface
    {
        $optimizedChildren = [];
        $hasChanged = false;

        foreach ($node->children as $child) {
            $optimizedChild = $child->accept($this);

            if ($optimizedChild instanceof Node\LiteralNode && \count($optimizedChildren) > 0) {
                $prevNode = $optimizedChildren[\count($optimizedChildren) - 1];
                if ($prevNode instanceof Node\LiteralNode) {
                    $optimizedChildren[\count($optimizedChildren) - 1] = new Node\LiteralNode(
                        $prevNode->value.$optimizedChild->value,
                        $prevNode->startPosition,
                        $optimizedChild->endPosition,
                    );
                    $hasChanged = true;

                    continue;
                }
            }

            if ($optimizedChild instanceof Node\SequenceNode) {
                array_push($optimizedChildren, ...$optimizedChild->children);
                $hasChanged = true;

                continue;
            }

            if ($optimizedChild instanceof Node\LiteralNode && '' === $optimizedChild->value) {
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

        if (1 === \count($optimizedChildren)) {
            return $optimizedChildren[0];
        }

        if (0 === \count($optimizedChildren)) {
            return new Node\LiteralNode('', $node->startPosition, $node->endPosition);
        }

        return new Node\SequenceNode($optimizedChildren, $node->startPosition, $node->endPosition);
    }

    /**
     * Optimizes a `GroupNode`.
     *
     * Purpose: This method simplifies groups where possible. Its main optimization is
     * to "unwrap" redundant non-capturing groups. For example, `(?:a)` is simplified
     * to just `a`, and `(?:[a-z])` becomes `[a-z]`, as the group serves no purpose.
     *
     * @param Node\GroupNode $node the group node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which might be the unwrapped child node)
     */
    public function visitGroup(Node\GroupNode $node): Node\NodeInterface
    {
        $optimizedChild = $node->child->accept($this);

        if (
            GroupType::T_GROUP_NON_CAPTURING === $node->type
            && ($optimizedChild instanceof Node\LiteralNode
                || $optimizedChild instanceof Node\CharTypeNode
                || $optimizedChild instanceof Node\DotNode)
        ) {
            return $optimizedChild;
        }

        if (
            GroupType::T_GROUP_NON_CAPTURING === $node->type
            && $optimizedChild instanceof Node\CharClassNode
        ) {
            return $optimizedChild;
        }

        if ($optimizedChild !== $node->child) {
            return new Node\GroupNode($optimizedChild, $node->type, $node->name, $node->flags, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * Optimizes a `QuantifierNode`.
     *
     * Purpose: This method recursively optimizes the node that the quantifier applies
     * to. Future optimizations could be added here, such as merging adjacent identical
     * quantifiers (e.g., `a?a?` -> `a{0,2}`) or simplifying nested quantifiers (e.g., `(a*)*` -> `a*`).
     *
     * @param Node\QuantifierNode $node the quantifier node to optimize
     *
     * @return Node\NodeInterface the new, optimized quantifier node
     */
    public function visitQuantifier(Node\QuantifierNode $node): Node\NodeInterface
    {
        $optimizedNode = $node->node->accept($this);

        if ($optimizedNode !== $node->node) {
            return new Node\QuantifierNode($optimizedNode, $node->quantifier, $node->type, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * Optimizes a `CharClassNode`.
     *
     * Purpose: This method simplifies character classes. For example, it can convert
     * a class containing a full digit range `[0-9]` into the more concise and efficient
     * `\d` token. It can also perform the same optimization for `\w`.
     *
     * @param Node\CharClassNode $node the character class node to optimize
     *
     * @return Node\NodeInterface the new, optimized node (which could be a `CharTypeNode`)
     */
    public function visitCharClass(Node\CharClassNode $node): Node\NodeInterface
    {
        $isUnicode = str_contains($this->flags, 'u');

        if (!$isUnicode && !$node->isNegated && 1 === \count($node->parts)) {
            $part = $node->parts[0];
            if ($part instanceof Node\RangeNode && $part->start instanceof Node\LiteralNode && $part->end instanceof Node\LiteralNode) {
                if ('0' === $part->start->value && '9' === $part->end->value) {
                    return new Node\CharTypeNode('d', $node->startPosition, $node->endPosition);
                }
            }
        }

        if (!$isUnicode && !$node->isNegated && 4 === \count($node->parts)) {
            if ($this->isFullWordClass($node)) {
                return new Node\CharTypeNode('w', $node->startPosition, $node->endPosition);
            }
        }

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
            return new Node\CharClassNode($optimizedParts, $node->isNegated, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * Visits a `RangeNode`.
     *
     * Purpose: Ranges are considered atomic and are not changed by the optimizer.
     * This method simply returns the node as is.
     *
     * @param Node\RangeNode $node the range node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitRange(Node\RangeNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Optimizes a `ConditionalNode`.
     *
     * Purpose: This method recursively optimizes the three branches of a conditional
     * node: the condition, the "yes" pattern, and the "no" pattern.
     *
     * @param Node\ConditionalNode $node the conditional node to optimize
     *
     * @return Node\NodeInterface the new, optimized conditional node
     */
    public function visitConditional(Node\ConditionalNode $node): Node\NodeInterface
    {
        $optimizedCond = $node->condition->accept($this);
        $optimizedYes = $node->yes->accept($this);
        $optimizedNo = $node->no->accept($this);

        if ($optimizedCond !== $node->condition || $optimizedYes !== $node->yes || $optimizedNo !== $node->no) {
            return new Node\ConditionalNode($optimizedCond, $optimizedYes, $optimizedNo, $node->startPosition, $node->endPosition);
        }

        return $node;
    }

    /**
     * Visits a `LiteralNode`.
     *
     * Purpose: Literals are atomic and cannot be optimized further by this visitor.
     * The merging of adjacent literals is handled by the `visitSequence` method.
     *
     * @param Node\LiteralNode $node the literal node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitLiteral(Node\LiteralNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `CharTypeNode`.
     *
     * Purpose: Character types like `\d` are already in their most optimal form.
     *
     * @param Node\CharTypeNode $node the character type node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitCharType(Node\CharTypeNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `DotNode`.
     *
     * Purpose: The `.` wildcard is atomic and cannot be optimized.
     *
     * @param Node\DotNode $node the dot node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitDot(Node\DotNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `AnchorNode`.
     *
     * Purpose: Anchors like `^` and `$` are atomic and cannot be optimized.
     *
     * @param Node\AnchorNode $node the anchor node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitAnchor(Node\AnchorNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `AssertionNode`.
     *
     * Purpose: Assertions like `\b` are atomic and cannot be optimized.
     *
     * @param Node\AssertionNode $node the assertion node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitAssertion(Node\AssertionNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `KeepNode`.
     *
     * Purpose: The `\K` assertion is atomic and cannot be optimized.
     *
     * @param Node\KeepNode $node the keep node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitKeep(Node\KeepNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `BackrefNode`.
     *
     * Purpose: Backreferences are dynamic and cannot be optimized.
     *
     * @param Node\BackrefNode $node the backreference node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitBackref(Node\BackrefNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `UnicodeNode`.
     *
     * Purpose: Unicode character escapes are atomic and cannot be optimized.
     *
     * @param Node\UnicodeNode $node the Unicode node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitUnicode(Node\UnicodeNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `UnicodePropNode`.
     *
     * Purpose: Unicode property escapes are atomic and cannot be optimized.
     *
     * @param Node\UnicodePropNode $node the Unicode property node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitUnicodeProp(Node\UnicodePropNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `OctalNode`.
     *
     * Purpose: Octal character escapes are atomic and cannot be optimized.
     *
     * @param Node\OctalNode $node the octal node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitOctal(Node\OctalNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits an `OctalLegacyNode`.
     *
     * Purpose: Legacy octal escapes are atomic and cannot be optimized.
     *
     * @param Node\OctalLegacyNode $node the legacy octal node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitOctalLegacy(Node\OctalLegacyNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `PosixClassNode`.
     *
     * Purpose: POSIX classes are atomic and cannot be optimized further.
     *
     * @param Node\PosixClassNode $node the POSIX class node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitPosixClass(Node\PosixClassNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `CommentNode`.
     *
     * Purpose: Comments do not affect matching and are preserved as is.
     *
     * @param Node\CommentNode $node the comment node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitComment(Node\CommentNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `SubroutineNode`.
     *
     * Purpose: Subroutine calls are dynamic and cannot be optimized.
     *
     * @param Node\SubroutineNode $node the subroutine node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitSubroutine(Node\SubroutineNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Visits a `PcreVerbNode`.
     *
     * Purpose: PCRE verbs control the matching engine and are not optimized.
     *
     * @param Node\PcreVerbNode $node the PCRE verb node
     *
     * @return Node\NodeInterface the unchanged node
     */
    public function visitPcreVerb(Node\PcreVerbNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * Optimizes a `DefineNode`.
     *
     * Purpose: This method recursively optimizes the content within a `(?(DEFINE)...)` block.
     *
     * @param Node\DefineNode $node the define node to optimize
     *
     * @return Node\NodeInterface the new, optimized define node
     */
    public function visitDefine(Node\DefineNode $node): Node\NodeInterface
    {
        return new Node\DefineNode(
            $node->content->accept($this),
            $node->startPosition,
            $node->endPosition,
        );
    }

    public function visitLimitMatch(Node\LimitMatchNode $node): Node\NodeInterface
    {
        return $node;
    }

    public function visitCallout(Node\CalloutNode $node): Node\NodeInterface
    {
        return $node;
    }

    /**
     * @param array<Node\NodeInterface> $alternatives
     */
    private function canAlternationBeCharClass(array $alternatives): bool
    {
        if (empty($alternatives)) {
            return false;
        }

        foreach ($alternatives as $alt) {
            if (!$alt instanceof Node\LiteralNode) {
                return false;
            }
            if (mb_strlen($alt->value) > 1) {
                return false;
            }
            if (isset(self::CHAR_CLASS_META[$alt->value])) {
                return false;
            }
        }

        return true;
    }

    private function isFullWordClass(Node\CharClassNode $node): bool
    {
        $partsFound = ['a-z' => false, 'A-Z' => false, '0-9' => false, '_' => false];
        foreach ($node->parts as $part) {
            if ($part instanceof Node\RangeNode && $part->start instanceof Node\LiteralNode && $part->end instanceof Node\LiteralNode) {
                $range = $part->start->value.'-'.$part->end->value;
                if (isset($partsFound[$range])) {
                    $partsFound[$range] = true;
                }
            } elseif ($part instanceof Node\LiteralNode && '_' === $part->value) {
                $partsFound['_'] = true;
            }
        }

        return !\in_array(false, $partsFound, true);
    }
}
