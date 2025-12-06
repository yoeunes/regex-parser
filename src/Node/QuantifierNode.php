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

namespace RegexParser\Node;

use RegexParser\NodeVisitor\NodeVisitorInterface;

/**
 * Represents a quantifier that modifies a preceding atom, such as `*`, `+`, `?`, or `{n,m}`.
 *
 * Purpose: This node indicates that the preceding element (its child node) can be matched a
 * variable number of times. It captures the quantifier itself (e.g., `*`, `{1,3}`), the type
 * of matching (greedy, lazy, or possessive), and the atom being quantified. This is a
 * fundamental concept in regex, and this node is crucial for analyzing potential performance
 * issues (like ReDoS) and for correctly compiling the pattern.
 */
final readonly class QuantifierNode extends AbstractNode
{
    /**
     * Initializes a quantifier node.
     *
     * Purpose: This constructor creates a node that applies a quantifier to another node (the "atom").
     *
     * @param NodeInterface  $node          The node representing the atom to be quantified (e.g., a `LiteralNode`
     *                                      for `a` in `a+`).
     * @param string         $quantifier    The base quantifier string, excluding any lazy or possessive modifiers
     *                                      (e.g., "*", "+", "?", "{1,3}").
     * @param QuantifierType $type          an enum indicating the matching strategy: `T_GREEDY` (match as much as
     *                                      possible), `T_LAZY` (match as little as possible), or `T_POSSESSIVE`
     *                                      (match as much as possible, without backtracking)
     * @param int            $startPosition the zero-based byte offset where the quantified atom begins
     * @param int            $endPosition   the zero-based byte offset immediately after the quantifier syntax
     */
    public function __construct(
        public NodeInterface $node,
        public string $quantifier,
        public QuantifierType $type,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `QuantifierNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitQuantifier` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitQuantifier($this);
    }
}
