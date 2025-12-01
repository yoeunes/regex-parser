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
 * Represents a range of characters within a character class, such as `a-z` in `[a-z0-9]`.
 *
 * Purpose: This node is a specific component of a `CharClassNode` that defines a continuous
 * sequence of characters between a start and an end point. It's a compact and efficient way
 * to specify large sets of characters. The `Parser` creates this node when it finds a hyphen
 * between two valid range endpoints inside a character class.
 */
readonly class RangeNode extends AbstractNode
{
    /**
     * Initializes a range node.
     *
     * Purpose: This constructor creates a node representing a `start-end` range within a character class.
     *
     * @param NodeInterface $start         The node representing the starting character of the range. This is
     *                                     typically a `LiteralNode` or `CharTypeNode`.
     * @param NodeInterface $end           The node representing the ending character of the range.
     * @param int           $startPosition The zero-based byte offset where the starting character of the range appears.
     * @param int           $endPosition   The zero-based byte offset immediately after the ending character of the range.
     */
    public function __construct(
        public NodeInterface $start,
        public NodeInterface $end,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `RangeNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitRange` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor The visitor object that is traversing the tree.
     *
     * @return T The result of the visitor's processing for this node.
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitRange($this);
    }
}
