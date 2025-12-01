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
 * Represents the dot (`.`) wildcard character in a regular expression.
 *
 * Purpose: This node represents one of the most common regex elements: the "match any character"
 * wildcard. By default, it matches any character except for a newline. If the "dotall" (`s`)
 * flag is enabled, it will also match newlines. Representing it as a distinct node allows
 * visitors to handle this special behavior correctly during analysis, compilation, or sample
 * generation.
 */
readonly class DotNode extends AbstractNode
{
    /**
     * Initializes a dot node.
     *
     * Purpose: This constructor creates a node representing the `.` wildcard. The `Parser`
     * generates this node when it encounters a `.` token. It simply records its position
     * in the original pattern.
     *
     * @param int $startPosition The zero-based byte offset where the `.` character appears.
     * @param int $endPosition   The zero-based byte offset immediately after the `.` character.
     */
    public function __construct(int $startPosition, int $endPosition)
    {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `DotNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitDot` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitDot($this);
    }
}
