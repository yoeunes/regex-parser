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
 * Represents a Unicode character property match, such as `\p{L}` (any letter) or `\P{N}` (not a number).
 *
 * Purpose: This node provides a powerful way to match characters based on their Unicode properties
 * rather than their literal value. This is essential for creating robust, internationalized regexes
 * that work correctly with a wide range of scripts and symbols. The node captures the property name
 * and whether it is negated (e.g., `\p` vs `\P`).
 */
readonly class UnicodePropNode extends AbstractNode
{
    /**
     * Initializes a Unicode property node.
     *
     * Purpose: This constructor creates a node for a `\p{...}` or `\P{...}` construct.
     *
     * @param string $prop          The name of the Unicode property, potentially with a negation prefix.
     *                              For example, for `\p{L}`, the value is "L". For `\P{L}`, the value is "^L".
     *                              For `\P{^L}` (double negative), the value is "L".
     * @param int    $startPosition the zero-based byte offset where the `\p` or `\P` sequence begins
     * @param int    $endPosition   the zero-based byte offset immediately after the sequence
     */
    public function __construct(
        public string $prop,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `UnicodePropNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitUnicodeProp` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitUnicodeProp($this);
    }
}
